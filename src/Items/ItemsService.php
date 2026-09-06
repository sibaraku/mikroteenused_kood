<?php

declare(strict_types=1);

namespace Laenutus\Items;

use Laenutus\Auth\AuthException;
use PDO;

final class ItemsService
{
    public function __construct(private readonly ?PDO $db = null) {}

    private function db(): PDO
    {
        return $this->db ?? \Laenutus\Database::connection();
    }

    /** @return list<array<string, mixed>> */
    public function list(?string $status = null): array
    {
        if ($status !== null) {
            $stmt = $this->db()->prepare('SELECT * FROM items WHERE status = :status ORDER BY name');
            $stmt->execute(['status' => $status]);
        } else {
            $stmt = $this->db()->query('SELECT * FROM items ORDER BY name');
        }

        return array_map([$this, 'format'], $stmt->fetchAll());
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        $item = $this->findRaw($id);
        if ($item === null) {
            throw new AuthException('NOT_FOUND', 'Vahendit ei leitud', 404);
        }

        return $this->format($item);
    }

    /** @return array<string, mixed>|null */
    public function findRaw(string $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch();

        return $item ?: null;
    }

    public function isAvailable(string $id): bool
    {
        $item = $this->findRaw($id);
        return $item !== null && $item['status'] === 'available';
    }

    public function reserve(string $id): void
    {
        $stmt = $this->db()->prepare(
            "UPDATE items SET status = 'reserved' WHERE id = :id AND status = 'available'"
        );
        $stmt->execute(['id' => $id]);
    }

    public function release(string $id): void
    {
        $stmt = $this->db()->prepare(
            "UPDATE items SET status = 'available' WHERE id = :id AND status = 'reserved'"
        );
        $stmt->execute(['id' => $id]);
    }

    /** @param array<string, mixed> $data */
    public function update(string $id, array $data): array
    {
        $item = $this->findRaw($id);
        if ($item === null) {
            throw new AuthException('NOT_FOUND', 'Vahendit ei leitud', 404);
        }

        $allowed = ['name', 'status', 'description', 'category'];
        $fields = [];
        $params = ['id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if ($fields === []) {
            return $this->format($item);
        }

        $validStatuses = ['available', 'reserved', 'broken', 'maintenance'];
        if (isset($params['status']) && !in_array($params['status'], $validStatuses, true)) {
            throw new AuthException('INVALID_STATUS', 'Vigane vahendi staatus', 400);
        }

        $sql = 'UPDATE items SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $this->get($id);
    }

    /** @param array<string, mixed> $row */
    private function format(array $row): array
    {
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'category' => $row['category'],
            'status' => $row['status'],
            'description' => $row['description'],
        ];
    }
}
