<?php

/*
 * This file is part of the Imagine package.
 *
 * (c) Bulat Shakirzyanov <mallluhuct@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JooosiSocialImageDeps\Imagine\Filter\Advanced;

use JooosiSocialImageDeps\Imagine\Filter\FilterInterface;
use JooosiSocialImageDeps\Imagine\Image\BoxInterface;
use JooosiSocialImageDeps\Imagine\Image\ImageInterface;
use JooosiSocialImageDeps\Imagine\Image\ImagineInterface;
use JooosiSocialImageDeps\Imagine\Image\Palette\Color\ColorInterface;
use JooosiSocialImageDeps\Imagine\Image\Point;
use JooosiSocialImageDeps\Imagine\Image\PointInterface;
/**
 * A canvas filter.
 */
class Canvas implements FilterInterface
{
    /**
     * @var \Imagine\Image\BoxInterface
     */
    private $size;
    /**
     * @var \Imagine\Image\PointInterface
     */
    private $placement;
    /**
     * @var \Imagine\Image\Palette\Color\ColorInterface
     */
    private $background;
    /**
     * @var \Imagine\Image\ImagineInterface
     */
    private $imagine;
    /**
     * Constructs Canvas filter with given width and height and the placement of the current image inside the new canvas.
     *
     * @param \Imagine\Image\ImagineInterface $imagine
     * @param \Imagine\Image\BoxInterface $size
     * @param \Imagine\Image\PointInterface $placement
     * @param \Imagine\Image\Palette\Color\ColorInterface $background
     */
    public function __construct(ImagineInterface $imagine, BoxInterface $size, ?PointInterface $placement = null, ?ColorInterface $background = null)
    {
        $this->imagine = $imagine;
        $this->size = $size;
        $this->placement = $placement ?: new Point(0, 0);
        $this->background = $background;
    }
    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image)
    {
        $canvas = $this->imagine->create($this->size, $this->background);
        $canvas->paste($image, $this->placement);
        return $canvas;
    }
}
