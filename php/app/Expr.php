<?php
/**
 * SQL 表达式包装：用于 Db::update 里做 "字段 = 字段 + N" 这类原子更新。
 */
declare(strict_types=1);

namespace App;

class Expr
{
    /** @var string */
    private $sql;

    public function __construct(string $sql)
    {
        $this->sql = $sql;
    }

    public function sql(): string
    {
        return $this->sql;
    }

    public function __toString(): string
    {
        return $this->sql;
    }
}
