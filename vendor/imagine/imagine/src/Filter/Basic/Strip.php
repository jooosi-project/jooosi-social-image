<?php

/*
 * This file is part of the Imagine package.
 *
 * (c) Bulat Shakirzyanov <mallluhuct@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JooosiEgamiDeps\Imagine\Filter\Basic;

use JooosiEgamiDeps\Imagine\Filter\FilterInterface;
use JooosiEgamiDeps\Imagine\Image\ImageInterface;
/**
 * A strip filter.
 */
class Strip implements FilterInterface
{
    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image)
    {
        return $image->strip();
    }
}
