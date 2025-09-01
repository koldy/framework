<?php declare(strict_types=1);

namespace Koldy\Validator;

use Koldy\Validator;

class Exception extends \Koldy\Exception
{

	/**
	 * @var Validator|null
	 */
	private Validator|null $validator = null;

	/**
	 * @return Validator
	 */
	public function getValidator(): Validator
	{
		return $this->validator;
	}

	/**
	 * @param Validator $validator
	 */
	public function setValidator(Validator $validator): void
	{
		$this->validator = $validator;
	}

}
