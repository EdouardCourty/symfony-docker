<?php

namespace App\Service\Trait;

trait RandomGeneratorTrait
{
    protected function generateToken(): string
    {
        return md5(uniqid() . '_' . microtime());
    }
}
