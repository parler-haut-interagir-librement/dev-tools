<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Each method moves something between a request, the database and a page: the lines the taint analysis reports end
 * with "reported".
 */
final class Notes
{
    public function __construct(private PDO $pdo)
    {
    }

    public function search(): void
    {
        $this->pdo->query("SELECT body FROM notes WHERE title = '".$_GET['title']."'"); // reported
    }

    public function searchBound(): void
    {
        $statement = $this->pdo->prepare('SELECT body FROM notes WHERE title = ?');
        $statement->execute([$_GET['title']]);
    }

    public function store(): void
    {
        $this->pdo->prepare('INSERT INTO notes (title, body) VALUES (?, ?)')->execute(['fixed', $_POST['body']]);
    }

    public function show(): void
    {
        $statement = $this->pdo->query('SELECT body FROM notes');
        $row = false === $statement ? false : $statement->fetch(PDO::FETCH_ASSOC);

        echo \is_array($row) ? $row['body'] : ''; // reported
    }

    public function showEscaped(): void
    {
        $statement = $this->pdo->query('SELECT body FROM notes');
        $row = false === $statement ? false : $statement->fetch(PDO::FETCH_ASSOC);

        echo \is_array($row) ? htmlspecialchars((string) $row['body']) : '';
    }
}
