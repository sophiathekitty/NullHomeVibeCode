<?php
/**
 * QueryBuilder — fluent SQL query builder backed by PDO prepared statements.
 *
 * Obtain an instance via a Model subclass:
 *   LightGroup::query()->where('active', 1)->orderBy('name')->get();
 *
 * All user-supplied values are bound via PDO prepared statements.
 * Field names are wrapped in backticks.
 * Operators are validated against an allowlist.
 * Sort directions are validated as ASC / DESC.
 */
class QueryBuilder
{
    // ── Allowlists ────────────────────────────────────────────────────────────

    private const ALLOWED_OPERATORS = ['=', '!=', '<', '>', '<=', '>='];

    // ── Internal state ────────────────────────────────────────────────────────

    private PDO $pdo;
    private string $table;

    /** @var string[] Selected columns; ['*'] means SELECT *. */
    private array $selectFields = ['*'];

    /**
     * Flat list of condition tokens.
     * Each item is one of:
     *   ['type' => 'condition', 'conjunction' => 'AND'|'OR', 'fragment' => string]
     *   ['type' => 'begin',     'conjunction' => 'AND'|'OR']
     *   ['type' => 'end']
     *
     * @var array<int, array<string, string>>
     */
    private array $conditions = [];

    /** Positional PDO bindings matching the ?-placeholders in $conditions. */
    private array $bindings = [];

    /** @var array<int, array{field: string, direction: string}> */
    private array $orderByClauses = [];

    private ?int $limitValue = null;

    /** Tracks open beginGroup() calls so we can detect imbalanced calls. */
    private int $groupDepth = 0;

    // ── Constructor ───────────────────────────────────────────────────────────

    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo   = $pdo;
        $this->table = $table;
    }

    // ── Chainable methods ─────────────────────────────────────────────────────

    /**
     * Specify the columns to SELECT.  Defaults to * if never called.
     */
    public function select(string ...$fields): static
    {
        if (!empty($fields)) {
            $this->selectFields = $fields;
        }
        return $this;
    }

    /**
     * Add a WHERE condition.
     *
     * @param string $field       Column name (wrapped in backticks).
     * @param mixed  $value       Value to compare (bound via PDO).
     * @param string $operator    One of: = != < > <= >=
     * @param string $conjunction AND or OR (how this joins the previous condition).
     * @throws \InvalidArgumentException For unsupported operators.
     */
    public function where(
        string $field,
        mixed $value,
        string $operator = '=',
        string $conjunction = 'AND'
    ): static {
        if (!in_array($operator, self::ALLOWED_OPERATORS, true)) {
            throw new \InvalidArgumentException(
                "Unsupported operator '{$operator}'. Allowed: "
                . implode(', ', self::ALLOWED_OPERATORS)
            );
        }

        $this->conditions[] = [
            'type'        => 'condition',
            'conjunction' => strtoupper($conjunction),
            'fragment'    => "`{$field}` {$operator} ?",
        ];
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Add a BETWEEN condition.
     *
     * @param string $field       Column name (wrapped in backticks).
     * @param mixed  $start       Lower bound (bound via PDO).
     * @param mixed  $end         Upper bound (bound via PDO).
     * @param string $conjunction AND or OR.
     */
    public function whereBetween(
        string $field,
        mixed $start,
        mixed $end,
        string $conjunction = 'AND'
    ): static {
        $this->conditions[] = [
            'type'        => 'condition',
            'conjunction' => strtoupper($conjunction),
            'fragment'    => "`{$field}` BETWEEN ? AND ?",
        ];
        $this->bindings[] = $start;
        $this->bindings[] = $end;
        return $this;
    }

    /**
     * Add a TIME(field) BETWEEN condition — useful for hourly aggregation
     * across multiple dates.
     *
     * @param string $field       Column name (wrapped in backticks).
     * @param string $start       Time string, e.g. '01:00:00'.
     * @param string $end         Time string, e.g. '01:59:59'.
     * @param string $conjunction AND or OR.
     */
    public function whereTimeBetween(
        string $field,
        string $start,
        string $end,
        string $conjunction = 'AND'
    ): static {
        $this->conditions[] = [
            'type'        => 'condition',
            'conjunction' => strtoupper($conjunction),
            'fragment'    => "TIME(`{$field}`) BETWEEN ? AND ?",
        ];
        $this->bindings[] = $start;
        $this->bindings[] = $end;
        return $this;
    }

    /**
     * Add a `field IS NULL` condition.
     *
     * @param string $field       Column name (wrapped in backticks).
     * @param string $conjunction AND or OR (how this joins the previous condition).
     * @return static
     */
    public function whereNull(string $field, string $conjunction = 'AND'): static
    {
        $this->conditions[] = [
            'type'        => 'condition',
            'conjunction' => strtoupper($conjunction),
            'fragment'    => "`{$field}` IS NULL",
        ];
        return $this;
    }

    /**
     * Add a `field IS NOT NULL` condition.
     *
     * @param string $field       Column name (wrapped in backticks).
     * @param string $conjunction AND or OR (how this joins the previous condition).
     * @return static
     */
    public function whereNotNull(string $field, string $conjunction = 'AND'): static
    {
        $this->conditions[] = [
            'type'        => 'condition',
            'conjunction' => strtoupper($conjunction),
            'fragment'    => "`{$field}` IS NOT NULL",
        ];
        return $this;
    }

    /**
     * Add a `field IN (v1, v2, ...)` condition.
     *
     * @param string   $field       Column name (wrapped in backticks).
     * @param array    $values      List of values to match (bound via PDO).
     * @param string   $conjunction AND or OR (how this joins the previous condition).
     * @return static
     */
    public function whereIn(string $field, array $values, string $conjunction = 'AND'): static
    {
        if (empty($values)) {
            $this->conditions[] = [
                'type'        => 'condition',
                'conjunction' => strtoupper($conjunction),
                'fragment'    => '1 = 0',
            ];
            return $this;
        }
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->conditions[] = [
            'type'        => 'condition',
            'conjunction' => strtoupper($conjunction),
            'fragment'    => "`{$field}` IN ({$placeholders})",
        ];
        foreach ($values as $value) {
            $this->bindings[] = $value;
        }
        return $this;
    }

    /**
     * Add a `field NOT IN (v1, v2, ...)` condition.
     *
     * @param string   $field       Column name (wrapped in backticks).
     * @param array    $values      List of values to exclude (bound via PDO).
     * @param string   $conjunction AND or OR (how this joins the previous condition).
     * @return static
     */
    public function whereNotIn(string $field, array $values, string $conjunction = 'AND'): static
    {
        if (empty($values)) {
            return $this;
        }
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->conditions[] = [
            'type'        => 'condition',
            'conjunction' => strtoupper($conjunction),
            'fragment'    => "`{$field}` NOT IN ({$placeholders})",
        ];
        foreach ($values as $value) {
            $this->bindings[] = $value;
        }
        return $this;
    }

    /**
     * Add a raw SQL fragment as a WHERE condition.
     *
     * The caller is responsible for using ? placeholders for any values.
     * Values are bound via PDO and appended to the binding list in order.
     *
     * @param string $fragment    Raw SQL condition fragment with ? placeholders.
     * @param array  $bindings    Positional values for the ? placeholders.
     * @param string $conjunction AND or OR (how this joins the previous condition).
     * @return static
     */
    public function whereRaw(string $fragment, array $bindings = [], string $conjunction = 'AND'): static
    {
        $this->conditions[] = [
            'type'        => 'condition',
            'conjunction' => strtoupper($conjunction),
            'fragment'    => $fragment,
        ];
        foreach ($bindings as $binding) {
            $this->bindings[] = $binding;
        }
        return $this;
    }

    /**
     * Open a parenthesised condition group.
     *
     * @param string $conjunction How this group joins the previous condition.
     */
    public function beginGroup(string $conjunction = 'AND'): static
    {
        $this->conditions[] = [
            'type'        => 'begin',
            'conjunction' => strtoupper($conjunction),
        ];
        $this->groupDepth++;
        return $this;
    }

    /**
     * Close the current condition group.
     *
     * @throws \RuntimeException If called without a matching beginGroup().
     */
    public function endGroup(): static
    {
        if ($this->groupDepth === 0) {
            throw new \RuntimeException(
                'endGroup() called without a matching beginGroup().'
            );
        }
        $this->conditions[] = ['type' => 'end'];
        $this->groupDepth--;
        return $this;
    }

    /**
     * Add an ORDER BY clause.
     *
     * @param string $field     Column name (wrapped in backticks).
     * @param string $direction ASC or DESC (case-insensitive).
     * @throws \InvalidArgumentException For directions other than ASC / DESC.
     */
    public function orderBy(string $field, string $direction = 'ASC'): static
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException(
                "Invalid ORDER BY direction '{$direction}'. Allowed: ASC, DESC."
            );
        }
        $this->orderByClauses[] = ['field' => $field, 'direction' => $direction];
        return $this;
    }

    /**
     * Limit the number of rows returned.  Omitted from the query if never called.
     */
    public function limit(int $limit): static
    {
        $this->limitValue = $limit;
        return $this;
    }

    // ── Terminal methods ──────────────────────────────────────────────────────

    /**
     * Execute the built SELECT query and return all matching rows.
     *
     * @return array<int, array<string, mixed>>
     * @throws \RuntimeException If groups are not balanced.
     */
    public function get(): array
    {
        $this->assertNoUnclosedGroups();

        $sql  = $this->buildSelect();
        $sql .= ' FROM `' . $this->table . '`';
        $sql .= $this->buildWhere();
        $sql .= $this->buildOrderBy();
        $sql .= $this->buildLimit();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute the query and return the first row, or null if no rows match.
     *
     * @return array<string, mixed>|null
     * @throws \RuntimeException If groups are not balanced.
     */
    public function first(): ?array
    {
        $rows = $this->limit(1)->get();
        return $rows[0] ?? null;
    }

    /**
     * Execute SELECT COUNT(*) using the current WHERE state.
     * Ignores SELECT columns, ORDER BY, and LIMIT.
     *
     * @throws \RuntimeException If groups are not balanced.
     */
    public function count(): int
    {
        $this->assertNoUnclosedGroups();

        $sql  = 'SELECT COUNT(*) FROM `' . $this->table . '`';
        $sql .= $this->buildWhere();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Execute DELETE using the current WHERE state.
     * Returns the number of affected rows.
     *
     * @throws \RuntimeException If groups are not balanced.
     */
    public function delete(): int
    {
        $this->assertNoUnclosedGroups();

        $sql  = 'DELETE FROM `' . $this->table . '`';
        $sql .= $this->buildWhere();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->rowCount();
    }

    /**
     * Execute UPDATE … SET … using the current WHERE state.
     * Returns the number of affected rows.
     *
     * @param array<string, mixed> $data Associative array of column => value pairs to set.
     * @return int Number of rows updated.
     * @throws \RuntimeException If groups are not balanced.
     */
    public function update(array $data): int
    {
        $this->assertNoUnclosedGroups();

        if (empty($data)) {
            return 0;
        }

        $setParts    = [];
        $setBindings = [];
        foreach ($data as $col => $value) {
            $setParts[]    = "`{$col}` = ?";
            $setBindings[] = $value;
        }

        $sql  = 'UPDATE `' . $this->table . '` SET ' . implode(', ', $setParts);
        $sql .= $this->buildWhere();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($setBindings, $this->bindings));
        return $stmt->rowCount();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Build the SELECT … column list.
     */
    private function buildSelect(): string
    {
        if ($this->selectFields === ['*']) {
            return 'SELECT *';
        }
        $cols = array_map(fn(string $f) => "`{$f}`", $this->selectFields);
        return 'SELECT ' . implode(', ', $cols);
    }

    /**
     * Build the WHERE clause, or an empty string if there are no conditions.
     *
     * Uses a stack of "is-first" flags to decide when to suppress the
     * conjunction for the first item at each nesting level.
     */
    private function buildWhere(): string
    {
        if (empty($this->conditions)) {
            return '';
        }

        $sql   = ' WHERE ';
        $stack = [true]; // true = "nothing emitted at this nesting level yet"

        foreach ($this->conditions as $cond) {
            $key     = array_key_last($stack);
            $isFirst = $stack[$key];

            switch ($cond['type']) {
                case 'begin':
                    if (!$isFirst) {
                        $sql .= ' ' . $cond['conjunction'] . ' ';
                    }
                    $sql .= '(';
                    $stack[$key] = false; // parent level: group counts as "something"
                    $stack[]     = true;  // child level: start fresh
                    break;

                case 'end':
                    array_pop($stack);
                    $sql .= ')';
                    break;

                case 'condition':
                    if (!$isFirst) {
                        $sql .= ' ' . $cond['conjunction'] . ' ';
                    }
                    $sql       .= $cond['fragment'];
                    $stack[$key] = false;
                    break;
            }
        }

        return $sql;
    }

    /**
     * Build the ORDER BY clause, or an empty string if none were added.
     */
    private function buildOrderBy(): string
    {
        if (empty($this->orderByClauses)) {
            return '';
        }
        $parts = array_map(
            fn(array $o) => "`{$o['field']}` {$o['direction']}",
            $this->orderByClauses
        );
        return ' ORDER BY ' . implode(', ', $parts);
    }

    /**
     * Build the LIMIT clause, or an empty string if never set.
     */
    private function buildLimit(): string
    {
        return $this->limitValue !== null ? ' LIMIT ' . $this->limitValue : '';
    }

    /**
     * Throw if there are unclosed beginGroup() calls.
     *
     * @throws \RuntimeException
     */
    private function assertNoUnclosedGroups(): void
    {
        if ($this->groupDepth > 0) {
            throw new \RuntimeException(
                'QueryBuilder has ' . $this->groupDepth . ' unclosed group(s). '
                . 'Call endGroup() before executing the query.'
            );
        }
    }
}
