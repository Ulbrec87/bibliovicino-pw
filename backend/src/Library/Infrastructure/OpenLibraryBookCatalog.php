<?php

declare(strict_types=1);

namespace App\Library\Infrastructure;

use App\Library\Application\Cover\ExternalCoverReferences;
use App\Library\Application\ExternalCatalog\ExternalBookCatalog;
use App\Library\Application\ExternalCatalog\ExternalCatalogPage;
use App\Library\Application\ExternalCatalog\ExternalCatalogUnavailable;
use App\Library\Application\ExternalCatalog\ExternalEditionSearchResult;
use App\Library\Application\ExternalCatalog\ExternalWorkSearchResult;
use App\Library\Domain\Author\Author;
use App\Library\Domain\Book\Isbn;
use App\Shared\Application\Clock;
use InvalidArgumentException;
use JsonException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class OpenLibraryBookCatalog implements ExternalBookCatalog
{
    private const PAGE_SIZE = 10;
    private const MAX_AUTHORS = 10;
    private const MAX_RESPONSE_BYTES = 2_000_000;

    private float $nextRequestAt = 0.0;

    public function __construct(
        private HttpClientInterface $client,
        private Clock $clock,
        private string $baseUrl,
        private string $coversBaseUrl,
        private string $userAgent,
        private float $timeout,
        private float $operationTimeout,
        private ExternalCoverReferences $coverReferences,
    ) {
    }

    public function search(string $query, int $page): ExternalCatalogPage
    {
        $literal = '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $query).'"';
        $data = $this->request('/search.json', [
            'q' => $literal,
            'lang' => 'it',
            'page' => $page,
            'limit' => self::PAGE_SIZE,
            'fields' => 'key,title,author_name,editions,editions.key,editions.title,editions.isbn,editions.publish_year,editions.cover_i',
        ], $this->deadline());
        if (!is_array($data['docs'] ?? null) || !array_is_list($data['docs'])) {
            throw new ExternalCatalogUnavailable('Malformed search.');
        }

        $results = [];
        $seenWorks = [];
        $seenIsbns = [];
        foreach (array_slice($data['docs'], 0, self::PAGE_SIZE) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $reference = $this->reference($row['key'] ?? null, 'W');
            $title = $this->text($row['title'] ?? null);
            if (null === $reference || '' === $title) {
                continue;
            }
            $edition = $this->suggestedEdition($row, $title);
            if (null === $edition) {
                continue;
            }
            if (isset($seenWorks[$reference]) || (null !== $edition->isbn && isset($seenIsbns[$edition->isbn]))) {
                continue;
            }
            $seenWorks[$reference] = true;
            if (null !== $edition->isbn) {
                $seenIsbns[$edition->isbn] = true;
            }
            $results[] = new ExternalWorkSearchResult($reference, $edition);
        }

        return new ExternalCatalogPage($results, $this->searchCount($data) > $page * self::PAGE_SIZE);
    }

    public function findByIsbn(string $canonicalIsbn): ?ExternalEditionSearchResult
    {
        $deadline = $this->deadline();
        $isbn = Isbn::fromString($canonicalIsbn)->toString();
        $data = $this->request('/isbn/'.$isbn.'.json', [], $deadline);
        if (null === $data) {
            return null;
        }
        $reference = $this->reference($data['key'] ?? null, 'M');
        if (null === $reference) {
            throw new ExternalCatalogUnavailable('Malformed edition.');
        }

        $candidates = $this->canonicalIsbns($data['isbn_13'] ?? [], $data['isbn_10'] ?? []);
        if ([] !== $candidates && !in_array($isbn, $candidates, true)) {
            throw new ExternalCatalogUnavailable('Mismatched ISBN.');
        }

        $authors = $this->findIsbnAuthors($isbn, $reference, $deadline);

        return $this->mapEdition($data, $reference, $isbn, $authors);
    }

    // Usiamo l'edizione suggerita da Open Library e gli autori indicati per l'opera.
    // Se l'edizione non ha un titolo, usiamo quello dell'opera.
    private function suggestedEdition(array $row, string $workTitle): ?ExternalEditionSearchResult
    {
        $candidate = $row['editions']['docs'][0] ?? null;
        if (!is_array($candidate)) {
            return null;
        }
        $reference = $this->reference($candidate['key'] ?? null, 'M');
        if (null === $reference) {
            return null;
        }
        $year = null;
        $years = is_array($candidate['publish_year'] ?? null) ? $candidate['publish_year'] : [];
        foreach (array_slice($years, 0, 30) as $value) {
            $year = $this->validYear($value);
            if (null !== $year) {
                break;
            }
        }

        $title = $this->text($candidate['title'] ?? null) ?: $workTitle;
        $rawAuthors = $row['author_name'] ?? [];
        $authors = $this->authorNames($rawAuthors);

        return new ExternalEditionSearchResult(
            reference: $reference,
            title: $title,
            authors: $authors,
            publicationYear: $year,
            isbn: $this->canonicalIsbns($candidate['isbn'] ?? [])[0] ?? null,
            coverPreviewUrl: $this->coverUrl($candidate['cover_i'] ?? null, 'M'),
            correctionRequired: $this->authorsIncomplete($rawAuthors, $authors),
            coverLargeUrl: $this->coverUrl($candidate['cover_i'] ?? null, 'L'),
            coverReference: $this->coverHandle($candidate['cover_i'] ?? null),
        );
    }

    //Cerchiamo gli autori con una richiesta separata, dopo aver verificato l'edizione tramite ISBN.
    private function findIsbnAuthors(string $isbn, string $reference, float $deadline): mixed
    {
        try {
            $data = $this->request('/search.json', [
                'q' => 'isbn:'.$isbn.' AND edition_key:'.$reference,
                'fields' => 'author_name,edition_key',
                'limit' => 1,
            ], $deadline);
            $row = $data['docs'][0] ?? null;
            // Usiamo questi autori solo se il risultato si riferisce anche all'edizione cercata.
            if (is_array($row) && is_array($row['edition_key'] ?? null) && in_array($reference, $row['edition_key'], true)) {
                return $row['author_name'] ?? [];
            }
        } catch (ExternalCatalogUnavailable) {
            // Se la ricerca degli autori fallisce, conserviamo i dati dell'edizione: l'utente potrà completarli a mano.
        }

        return [];
    }

    private function mapEdition(array $data, string $reference, string $isbn, mixed $rawAuthors): ExternalEditionSearchResult
    {
        $title = $this->text($data['title'] ?? null);
        $authors = $this->authorNames($rawAuthors);
        $coverId = null;
        foreach (is_array($data['covers'] ?? null) ? $data['covers'] : [] as $id) {
            if (is_int($id) && $id > 0) {
                $coverId = $id;
                break;
            }
        }

        return new ExternalEditionSearchResult(
            reference: $reference,
            title: $title,
            authors: $authors,
            publicationYear: $this->editionYear($data['publish_date'] ?? null),
            isbn: $isbn,
            coverPreviewUrl: $this->coverUrl($coverId, 'M'),
            correctionRequired: '' === $title || $this->authorsIncomplete($rawAuthors, $authors),
            coverLargeUrl: $this->coverUrl($coverId, 'L'),
            coverReference: $this->coverHandle($coverId),
        );
    }

    private function validYear(mixed $value): ?int
    {
        return is_int($value) && $value >= 1000 && $value <= (int) $this->clock->now()->format('Y') ? $value : null;
    }

    // La data di pubblicazione può contenere anche giorno e mese; a noi serve solo l'anno.
    private function editionYear(mixed $date): ?int
    {
        if (!is_string($date) || mb_strlen($date) > 255) {
            return null;
        }
        if (!preg_match('/(?<![0-9])([1-2][0-9]{3})(?![0-9])/', $date, $match)) {
            return null;
        }

        return $this->validYear((int) $match[1]);
    }

    // Per la scelta della copertina generiamo un riferimento che l'applicazione potrà verificare al salvataggio.
    private function coverHandle(mixed $id): ?string
    {
        return is_int($id) && $id > 0 ? $this->coverReferences->issue($id) : null;
    }

    private function coverUrl(mixed $id, string $size): ?string
    {
        return is_int($id) && $id > 0 ? rtrim($this->coversBaseUrl, '/').'/b/id/'.$id.'-'.$size.'.jpg?default=false' : null;
    }

    // Consideriamo solo i primi dieci nomi ricevuti, anche se alcuni vengono poi scartati perché non validi o duplicati.
    /** @return list<string> */
    private function authorNames(mixed $values): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            return [];
        }
        $names = [];
        $seen = [];
        foreach (array_slice($values, 0, self::MAX_AUTHORS) as $value) {
            $name = $this->text($value);
            if ('' === $name) {
                continue;
            }
            try {
                $name = Author::normaliseName($name);
            } catch (InvalidArgumentException) {
                continue;
            }
            $key = mb_strtolower($name);
            if (!isset($seen[$key])) {
                $names[] = $name;
                $seen[$key] = true;
            }
        }

        return $names;
    }

    // Chiediamo di verificare gli autori anche quando abbiamo scartato dei nomi, compresi i duplicati.
    private function authorsIncomplete(mixed $rawAuthors, array $authors): bool
    {
        return [] === $authors || !is_array($rawAuthors) || !array_is_list($rawAuthors) || count($rawAuthors) !== count($authors);
    }

    //  Convertiamo anche gli ISBN-10 in ISBN-13, così lo stesso ISBN compare una sola volta.
    /** @return list<string> */
    private function canonicalIsbns(mixed ...$lists): array
    {
        $isbns = [];
        foreach ($lists as $list) {
            if (!is_array($list)) {
                continue;
            }
            foreach (array_slice($list, 0, 30) as $value) {
                if (!is_string($value) || strlen($value) > 40) {
                    continue;
                }
                try {
                    $isbns[] = Isbn::fromString($value)->toString();
                } catch (InvalidArgumentException) {
                }
            }
        }

        return array_values(array_unique($isbns));
    }

    // Open Library può restituire sia un ID sia un percorso, ad esempio OL123M oppure /books/OL123M.
    private function reference(mixed $value, string $type): ?string
    {
        if (!is_string($value) || strlen($value) > 64) {
            return null;
        }
        $prefix = ['W' => '/works/', 'M' => '/books/'][$type];
        if (str_starts_with($value, $prefix)) {
            $value = substr($value, strlen($prefix));
        }

        if (!str_starts_with($value, 'OL') || !str_ends_with($value, $type)) {
            return null;
        }
        $number = substr($value, 2, -1);

        return ctype_digit($number) && !str_starts_with($number, '0') ? $value : null;
    }

    // Trattiamo i testi non validi o troppo lunghi come dati mancanti, senza troncarli.
    private function text(mixed $value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return '';
        }
        $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '');
        return mb_strlen($value) <= 255 ? $value : '';
    }

    // Open Library usa due nomi per il totale dei risultati: accettiamo sia numFound sia num_found.
    private function searchCount(array $data): int
    {
        $count = $data['numFound'] ?? $data['num_found'] ?? null;
        if (!is_int($count) || $count < 0) {
            throw new ExternalCatalogUnavailable('Malformed search count.');
        }

        return $count;
    }

    // Tutte le richieste della stessa operazione condividono un unico limite di tempo, che include attese e redirect.
    private function deadline(): float
    {
        return microtime(true) + $this->operationTimeout;
    }

    // Il client può seguire fino a due redirect, anche verso altri domini, usando il tempo ancora disponibile.
    private function request(string $path, array $query, float $deadline): ?array
    {
        try {
            // Questa istanza lascia passare almeno un secondo tra le richieste; i redirect sono gestiti direttamente dal client.
            $pause = max(0, $this->nextRequestAt - microtime(true));
            if (microtime(true) + $pause >= $deadline) {
                throw new ExternalCatalogUnavailable('Operation timed out.');
            }
            if ($pause > 0) {
                usleep((int) ceil($pause * 1000000));
            }
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                throw new ExternalCatalogUnavailable('Operation timed out.');
            }
            $requestTimeout = min($this->timeout, $remaining);
            $this->nextRequestAt = microtime(true) + 1.0;

            $response = $this->client->request('GET', rtrim($this->baseUrl, '/').$path, [
                'query' => $query,
                'headers' => ['Accept' => 'application/json', 'User-Agent' => $this->userAgent],
                'timeout' => $requestTimeout,
                'max_duration' => $requestTimeout,
                'max_redirects' => 2,
            ]);
            $status = $response->getStatusCode();
            if (404 === $status) {
                $response->cancel();
                return null;
            }
            if (200 !== $status) {
                $response->cancel();
                throw new ExternalCatalogUnavailable('Provider returned HTTP '.$status.'.');
            }

            return $this->readResponse($response, $requestTimeout);
        } catch (ExceptionInterface|JsonException $exception) {
            throw new ExternalCatalogUnavailable('Provider unavailable.', previous: $exception);
        }
    }

    // Controlliamo la dimensione mentre leggiamo la risposta, per interrompere subito il download se supera il limite.
    private function readResponse(ResponseInterface $response, float $timeout): array
    {
        $content = '';
        foreach ($this->client->stream($response, $timeout) as $chunk) {
            if ($chunk->isTimeout()) {
                $response->cancel();
                throw new ExternalCatalogUnavailable('Provider timed out.');
            }
            $content .= $chunk->getContent();
            if (strlen($content) > self::MAX_RESPONSE_BYTES) {
                $response->cancel();
                throw new ExternalCatalogUnavailable('Response too large.');
            }
        }

        $data = json_decode($content, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_is_list($data)) {
            throw new ExternalCatalogUnavailable('Malformed response.');
        }

        return $data;
    }
}
