<?php

declare(strict_types=1);

namespace App\Library\Application\CreateBookCopy;

use App\Library\Application\Cover\CoverChoice;
use App\Library\Domain\Author\Author;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\Isbn;
use App\Library\Domain\Category\CategoryId;
use App\Shared\Application\Clock;
use InvalidArgumentException;

final readonly class CreateBookCopyCommandFactory
{
    public function __construct(private Clock $clock, private CoverChoice $coverChoice)
    {
    }

    /** @param array<string, mixed> $input */
    public function fromArray(string $ownerId, array $input): CreateBookCopyCommand
    {
        try {
            $cover = $this->coverChoice->parse($input['cover'] ?? null);
        } catch (InvalidArgumentException) {
            throw new InvalidBookCopyInput(['cover' => ['Seleziona una copertina valida oppure nessuna copertina.']]);
        }
        $errors = [];
        $this->validateCommonFields($input, $errors);
        $bookId = $this->parseBookId(($input['bookId'] ?? null), $errors);
        $newBook = $this->parseNewBook(($input['newBook'] ?? null), $errors);
        if ([] !== $errors) {
            throw new InvalidBookCopyInput($errors);
        }

        return new CreateBookCopyCommand($ownerId, $bookId, $newBook, $input['notes'] ?? null, $input['publish'] ?? null, $cover);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, list<string>> $errors
     */
    private function validateCommonFields(array $input, array &$errors): void
    {
        if ((null !== ($input['bookId'] ?? null)) === (null !== ($input['newBook'] ?? null))) {
            $errors['book'][] = 'Seleziona un libro esistente oppure aggiungine uno nuovo.';
        }
        if (array_key_exists('ownerId', $input) || array_key_exists('owner', $input)) {
            $errors['owner'][] = 'Il proprietario viene determinato dalla sessione.';
        }
        if (!is_bool($input['publish'] ?? null)) {
            $errors['publish'][] = 'Indica un valore valido.';
        }
        foreach (['condition', 'offeredForExchange', 'offeredForLoan'] as $removedField) {
            if (array_key_exists($removedField, $input)) {
                $errors[$removedField][] = 'Questo campo non è più supportato.';
            }
        }
        $notes = $input['notes'] ?? null;
        if (null !== $notes && (!is_string($notes) || mb_strlen($notes) > 1100)) {
            $errors['notes'][] = 'Le note non possono superare 1100 caratteri.';
        }
    }

    /** @param array<string, list<string>> $errors */
    private function parseBookId(mixed $value, array &$errors): ?BookId
    {
        if (null === $value) {
            return null;
        }
        try {
            if (!is_string($value)) {
                throw new InvalidArgumentException();
            }
            return BookId::fromString($value);
        } catch (InvalidArgumentException) {
            $errors['bookId'][] = 'Seleziona un libro valido.';
            return null;
        }
    }

    /** @param array<string, list<string>> $errors */
    private function parseNewBook(mixed $input, array &$errors): ?NewBookData
    {
        if (null === $input) {
            return null;
        }
        if (!is_array($input)) {
            $errors['newBook'][] = 'Inserisci i dati del libro.';
            return null;
        }

        $bookErrors = [];
        $title = $input['title'] ?? null;
        if (!is_string($title) || mb_strlen(trim($title)) < 1 || mb_strlen(trim($title)) > 255) {
            $bookErrors['newBook.title'][] = 'Inserisci un titolo di massimo 255 caratteri.';
        }
        $authors = $this->parseAuthors(($input['authors'] ?? null), $bookErrors);
        $categoryIds = $this->parseCategoryIds(($input['categoryIds'] ?? null), $bookErrors);
        $year = $input['publicationYear'] ?? null;
        if (null !== $year && (!is_int($year) || $year < 1000 || $year > (int) $this->clock->now()->format('Y'))) {
            $bookErrors['newBook.publicationYear'][] = 'Indica un anno compreso tra 1000 e quello attuale.';
        }
        $isbn = $this->parseIsbn(($input['isbn'] ?? null), $bookErrors);
        if ([] !== $bookErrors) {
            $errors = array_merge($errors, $bookErrors);
            return null;
        }

        return new NewBookData($title, $authors, $categoryIds, $year, $isbn);
    }

    /**
     * @param array<string, list<string>> $errors
     * @return list<string>
     */
    private function parseAuthors(mixed $input, array &$errors): array
    {
        if (!is_array($input) || !array_is_list($input) || count($input) < 1 || count($input) > 10) {
            $errors['newBook.authors'][] = 'Inserisci da 1 a 10 autori.';
            return [];
        }
        $authors = [];
        $seen = [];
        foreach ($input as $name) {
            try {
                if (!is_string($name)) {
                    throw new InvalidArgumentException();
                }
                $normalised = Author::normaliseName($name);
                $canonical = mb_strtolower($normalised);
                if (isset($seen[$canonical])) {
                    throw new InvalidArgumentException();
                }
                $seen[$canonical] = true;
                $authors[] = $normalised;
            } catch (InvalidArgumentException) {
                $errors['newBook.authors'][] = 'Indica autori distinti con nomi validi.';
                return [];
            }
        }
        return $authors;
    }

    /**
     * @param array<string, list<string>> $errors
     * @return list<CategoryId>
     */
    private function parseCategoryIds(mixed $input, array &$errors): array
    {
        if (!is_array($input) || !array_is_list($input) || [] === $input) {
            $errors['newBook.categoryIds'][] = 'Seleziona almeno una categoria.';
            return [];
        }
        $ids = [];
        $seen = [];
        foreach ($input as $value) {
            try {
                if (!is_string($value)) {
                    throw new InvalidArgumentException();
                }
                $id = CategoryId::fromString($value);
                if (isset($seen[$id->toString()])) {
                    throw new InvalidArgumentException();
                }
                $seen[$id->toString()] = true;
                $ids[] = $id;
            } catch (InvalidArgumentException) {
                $errors['newBook.categoryIds'][] = 'Seleziona categorie valide e distinte.';
                return [];
            }
        }
        return $ids;
    }

    /** @param array<string, list<string>> $errors */
    private function parseIsbn(mixed $input, array &$errors): ?Isbn
    {
        if (null === $input) {
            return null;
        }
        if (!is_string($input)) {
            $errors['newBook.isbn'][] = 'Inserisci un ISBN valido.';
            return null;
        }
        if ('' === trim($input)) {
            return null;
        }
        try {
            return Isbn::fromString($input);
        } catch (InvalidArgumentException) {
            $errors['newBook.isbn'][] = 'Inserisci un ISBN-10 o ISBN-13 valido.';
            return null;
        }
    }
}
