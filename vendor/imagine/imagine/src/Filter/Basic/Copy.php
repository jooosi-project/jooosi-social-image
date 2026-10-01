<?php

/*
 * This file is part of the Imagine package.
 *
 * (c) Bulat Shakirzyanov <mallluhuct@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JooosiSocialImageDeps\Imagine\Filter\Basic;

use JooosiSocialImageDeps\Imagine\Filter\FilterInterface;
use JooosiSocialImageDeps\Imagine\Image\ImageInterface;
/**
 * A copy filter.
 */
class Copy implements FilterInterface
{
    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image)
    {
        return $image->copy();
    }
}
