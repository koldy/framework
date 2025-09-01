<?php declare(strict_types=1);

namespace Koldy\Db;

use Stringable;

/**
 * The expression data holder - string stored in this class will be
 * literally printed in query with no additional adding slashes or anything similar.
 *
 */
class Expr implements Stringable
{

	/**
	 * Construct the object
	 *
	 * @param string $expression
	 */
	public function __construct(private string $expression)
	{
	}

	/**
	 * Print the data as is
	 * @return string
	 */
	public function __toString(): string
	{
		return $this->getExpression();
	}

	/**
	 * Get the data
	 * @return string
	 */
	public function getExpression(): string
	{
		return $this->expression;
	}

}
