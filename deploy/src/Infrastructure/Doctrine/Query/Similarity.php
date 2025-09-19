<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Query;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

/**
 * Custom DQL function for PostgreSQL SIMILARITY text comparison
 */
class Similarity extends FunctionNode
{
    public $field1 = null;
    public $field2 = null;

    public function parse(Parser $parser): void
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->field1 = $parser->ArithmeticPrimary();
        $parser->match(Lexer::T_COMMA);
        $this->field2 = $parser->ArithmeticPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return 'similarity(' . 
               $this->field1->dispatch($sqlWalker) . ', ' . 
               $this->field2->dispatch($sqlWalker) . ')';
    }
}
