<?php
namespace Fei\Service\Translate\Client\Utils;

use Doctrine\Common\Collections\ArrayCollection as Collection;

class ArrayCollection extends Collection
{
    public function __toString()
    {
        return (string) $this->first();
    }
}