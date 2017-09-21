<?php declare(strict_types=1);

namespace Koldy\Db;

/**
 * The expression data holder - string stored in this class will be
 * literally printed in query with no additional adding slashes or anything similar.
 *
 */
class Expr
{

    /**
     * @var string
     */
    private $expression;

    /**
     * Construct the object
     *
     * @param string $expression
     */
    public function __construct(string $expression)
    {
        $this->expression = $expression;
    }

    /**
     * Get the data
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Get the data
     * @return string
     * @deprecated Deprecated since 2.0. Use getExpression() instead.
     */
    public function getData(): string
    {
        return $this->getExpression();
    }

    /**
     * Print the data as is
     * @return string
     */
    public function __toString(): string
    {
        return $this->getExpression();
    }

}
