<?php

/**
 * @brief Interface that abstracts the implementation of drawing operations
 *
 * It's not clear whether this could be replaced with PEAR
 * Image_Canvas. If it's possible to do so, this would make it a lot
 * easier to maintain implementation independence
 */
interface ICanvas
{
    public function drawRectangle(Point $corner1, Point $corner2, Color $color, $lineWidth, $lineDotSize, ShadowProperties $shadowProperties);

    public function drawFilledRectangle(
        Point $corner1,
        Point $corner2,
        Color $color,
        ShadowProperties $shadowProperties,
        $drawBorder = false,
        $alpha = 100,
        $lineWidth = 1,
        $lineDotSize = 0
    );

    public function drawRoundedRectangle(
        Point $corner1,
        Point $corner2,
        $radius,
        Color $color,
        $lineWidth,
        $lineDotSize,
        ShadowProperties $shadowProperties
    );

    public function drawFilledRoundedRectangle(
        Point $point1,
        Point $point2,
        $radius,
        Color $color,
        $lineWidth,
        $lineDotSize,
        ShadowProperties $shadowProperties
    );

    public function drawLine(Point $point1, Point $point2, Color $color, $lineWidth, $lineDotSize, ShadowProperties $shadowProperties, Point $boundingBoxMin = null, Point $boundingBoxMax = null);

    public function drawDottedLine(Point $point1, Point $point2, $dotSize, $lineWidth, Color $color, ShadowProperties $shadowProperties, Point $boundingBoxMin = null, Point $boundingBoxMax = null);

    public function drawAntialiasPixel(Point $point, Color $color, ShadowProperties $shadowProperties, $alpha = 100);

    public function drawText($fontSize, $angle, Point $point, Color $color, $fontName, $text, ShadowProperties $shadowProperties);

    /**
     * @todo The function's called drawCircle(), but you can make it
     * draw an ellipse by passing in different values for width and
     * height. This should be changed.
     */
    public function drawCircle(Point $center, $height, Color $color, ShadowProperties $shadowProperties, $width = null);

    public function drawFilledCircle(Point $center, $height, Color $color, ShadowProperties $shadowProperties, $width = null);

    /**
     * Draw a filled polygon
     *
     * @todo The points are passed in as an array of X, Y, X, Y
     * consecutive coordinates. This interface sucks, and should be
     * replaced with passing in an arry of instances of Point
     */
    public function drawFilledPolygon(array $points, $numPoints, Color $color, $alpha = 100);

    public function setAntialiasQuality($newQuality);
}
