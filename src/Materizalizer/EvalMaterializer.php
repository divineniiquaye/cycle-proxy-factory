<?php

namespace Cycle\ORM\Promise\Materizalizer;

use Cycle\ORM\Promise\Declaration\Declaration;
use Cycle\ORM\Promise\MaterializerInterface;

final class EvalMaterializer implements MaterializerInterface
{
    /**
     * {@inheritdoc}
     * If class already exists - do nothing (prevent from memory leaking)
     */
    public function materialize(string $code, Declaration $declaration): void
    {
        if (class_exists($declaration->class->getFullName())) {
            return;
        }

        if (mb_strpos($code, '<?php') === 0) {
            $code = mb_substr($code, 5);
        } elseif (mb_strpos($code, '<?') === 0) {
            $code = mb_substr($code, 2);
        }

        eval($code);
    }
}