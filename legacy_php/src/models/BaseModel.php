<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Base Model
 * Provides CRUD operations and database interaction
 */
abstract class BaseModel {
    protected $db;
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $where_clauses = [];
    protected string $where_sql = '';
    protected array $where_params = [];
    protected string $where_types = '';
    protected string $order_by = '';
    protected int $limit_count = 0;
    protected int $limit_offset = 0;
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Find by primary key
     */
    public function find($id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Get all records
     */
    public function all(): array {
        $query = "SELECT * FROM {$this->table}";
        
        if (!empty($this->order_by)) {
            $query .= " ORDER BY {$this->order_by}";
        }
        
        if ($this->limit_count > 0) {
            $query .= " LIMIT {$this->limit_offset}, {$this->limit_count}";
        }
        
        $result = $this->db->query($query);
        $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $this->resetQuery();

        return $data;
    }
    
    /**
     * Create new record
     */
    public function create(array $data): ?int {
        // Filter fillable fields
        $data = array_intersect_key($data, array_flip($this->fillable));
        
        if (empty($data)) {
            return null;
        }
        
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $columns_str = implode(', ', $columns);
        $placeholders_str = implode(', ', $placeholders);
        
        $types = $this->getTypes($values);
        
        $query = "INSERT INTO {$this->table} ({$columns_str}) VALUES ({$placeholders_str})";
        $stmt = $this->db->prepare($query);
        
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        
        return null;
    }
    
    /**
     * Update record
     */
    public function update(int $id, array $data): bool {
        // Filter fillable fields
        $data = array_intersect_key($data, array_flip($this->fillable));
        
        if (empty($data)) {
            return false;
        }
        
        $columns = array_keys($data);
        $values = array_values($data);
        
        $set_clauses = array_map(function($col) {
            return "$col = ?";
        }, $columns);
        
        $values[] = $id;
        $types = $this->getTypes($values);
        
        $set_str = implode(', ', $set_clauses);
        
        $query = "UPDATE {$this->table} SET {$set_str} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($query);
        
        $stmt->bind_param($types, ...$values);
        
        return $stmt->execute();
    }
    
    /**
     * Delete record
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->bind_param("i", $id);
        
        return $stmt->execute();
    }
    
    /**
     * Add WHERE clause
     */
    public function where(string $column, string $operator, $value): self {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }

        $allowedOperators = ['=', '!=', '>', '>=', '<', '<=', 'LIKE'];
        if (!in_array(strtoupper($operator), $allowedOperators, true)) {
            throw new \InvalidArgumentException("Invalid operator: {$operator}");
        }

        $this->where_clauses[] = [$column, $operator, $value];
        return $this;
    }
    
    /**
     * Build and execute query with WHERE clauses
     */
    public function get(): array {
        if (empty($this->where_clauses)) {
            return $this->all();
        }
        
        $query = "SELECT * FROM {$this->table}";
        $where_parts = [];
        $values = [];
        $types = '';
        
        foreach ($this->where_clauses as $clause) {
            [$column, $operator, $value] = $clause;
            $where_parts[] = "$column $operator ?";
            $values[] = $value;
            $types .= $this->getType($value);
        }
        
        $query .= " WHERE " . implode(" AND ", $where_parts);
        
        if (!empty($this->order_by)) {
            $query .= " ORDER BY {$this->order_by}";
        }
        
        if ($this->limit_count > 0) {
            $query .= " LIMIT {$this->limit_offset}, {$this->limit_count}";
        }
        
        $stmt = $this->db->prepare($query);
        
        if (!empty($values)) {
            $stmt->bind_param($types, ...$values);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $this->resetQuery();

        return $data;
    }
    
    /**
     * Get first result
     */
    public function first(): ?array {
        $clone = clone $this;
        $clone->limit_count = 1;
        $results = $clone->get();
        
        return $results[0] ?? null;
    }
    
    /**
     * Count records
     */
    public function count(): int {
        if (empty($this->where_clauses)) {
            $result = $this->db->query("SELECT COUNT(*) as count FROM {$this->table}");
        } else {
            $query = "SELECT COUNT(*) as count FROM {$this->table}";
            $where_parts = [];
            $values = [];
            $types = '';
            
            foreach ($this->where_clauses as $clause) {
                [$column, $operator, $value] = $clause;
                $where_parts[] = "$column $operator ?";
                $values[] = $value;
                $types .= $this->getType($value);
            }
            
            $query .= " WHERE " . implode(" AND ", $where_parts);
            
            $stmt = $this->db->prepare($query);
            
            if (!empty($values)) {
                $stmt->bind_param($types, ...$values);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
        }
        
        $row = $result->fetch_assoc();
        $this->resetQuery();
        
        return (int)($row['count'] ?? 0);
    }
    
    /**
     * Order by
     */
    public function orderBy(string $column, string $direction = 'ASC'): self {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name for order by: {$column}");
        }

        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        $this->order_by = "$column $direction";
        return $this;
    }
    
    /**
     * Limit results
     */
    public function limit(int $count, int $offset = 0): self {
        $this->limit_count = $count;
        $this->limit_offset = $offset;
        return $this;
    }
    
    /**
     * Paginate
     */
    public function paginate(int $page = 1, int $perPage = 10): array {
        $offset = ($page - 1) * $perPage;
        
        $total = $this->count();
        $clone = clone $this;
        $clone->limit($perPage, $offset);
        $data = $clone->get();
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage)
        ];
    }
    
    /**
     * Get parameter types for bind_param
     */
    private function getTypes(array $values): string {
        $types = '';
        foreach ($values as $value) {
            $types .= $this->getType($value);
        }
        return $types;
    }
    
    /**
     * Get single parameter type
     */
    private function getType($value): string {
        if (is_int($value)) {
            return 'i';
        } elseif (is_float($value)) {
            return 'd';
        }
        return 's';
    }
    
    /**
     * Reset query builder
     */
    protected function resetQuery(): void {
        $this->where_clauses = [];
        $this->order_by = '';
        $this->limit_count = 0;
        $this->limit_offset = 0;
    }
}
?>
