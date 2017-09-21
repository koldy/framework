<?php declare(strict_types=1);

namespace Koldy\Validator;

use Koldy\Validator;

class Exception extends \Koldy\Exception
{

    /**
     * @var Validator
     */
    private $validator = null;

    /**
     * @param Validator $validator
     */
    public function setValidator(Validator $validator): void
    {
        $this->validator = $validator;
    }

    /**
     * @return Validator
     */
    public function getValidator(): Validator
    {
        return $this->validator;
    }

}
