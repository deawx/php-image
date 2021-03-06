<?php

namespace Sokil;

use PHPUnit\Framework\TestCase;
use \Sokil\Image\ColorModel\Rgb;
use Sokil\Image\Exception\ImageException;
use Sokil\Image\Factory;
use Sokil\Image\WriteStrategy\GifWriteStrategy;
use Sokil\Image\WriteStrategy\JpegWriteStrategy;
use Sokil\Image\WriteStrategy\PngWriteStrategy;

class ImageTest extends TestCase
{
    /**
     *
     * @var Factory
     */
    protected $factory;
    
    public function setUp(): void
    {
        $this->factory = new Factory();
    }

    public function testLoadFile_UnexistedFile()
    {
        $this->expectException(ImageException::class);
        $this->expectExceptionMessage('File /some-unexisted-file.jpg not found');

        $this->factory->openImage('/some-unexisted-file.jpg');
    }
    
    public function testWrite_Jpeg()
    {
        $sourceFilename = __DIR__ . '/test.jpg';
        $targetFilename = sys_get_temp_dir() . '/sokil-php-image.jpg';
        
        $image = $this->factory->openImage($sourceFilename);
        $this->factory->writeImage(
            $image, 
            'jpeg', 
            function(JpegWriteStrategy $writeStrategy) use($targetFilename) {
                $writeStrategy->setQuality(100)->toFile($targetFilename);
            }
        );
            
        // check file existance
        $this->assertFileExists($targetFilename);
        
        // check image
        $this->assertEquals(
            getimagesize($sourceFilename), 
            getimagesize($targetFilename)
        );
    }

    public function testWrite_Jpeg_Stdout()
    {
        $sourceFilename = __DIR__ . '/test.jpg';
        $targetFilename = sys_get_temp_dir() . '/sokil-php-image.jpg';

        $image = $this->factory->openImage($sourceFilename);

        ob_start();

        $this->factory->writeImage(
            $image,
            'jpeg',
            function(JpegWriteStrategy $writeStrategy) use($targetFilename) {
                $writeStrategy->setQuality(100)->toStdout();
            }
        );

        $content = ob_get_clean();

        // check file existance
        $this->assertNotEmpty($content);


        // check image
        file_put_contents($targetFilename, $content);

        $this->assertEquals(
            getimagesize($sourceFilename),
            getimagesize($targetFilename)
        );
    }
    
    public function testWrite_Gif()
    {
        $sourceFilename = __DIR__ . '/test.gif';
        $targetFilename = sys_get_temp_dir() . '/sokil-php-image.gif';
        
        $image = $this->factory->openImage($sourceFilename);
        $this->factory->writeImage(
            $image,
            'gif', 
            function(GifWriteStrategy $writeStrategy) use($targetFilename) {
                $writeStrategy->toFile($targetFilename);
            }
        );
            
        // check file existance
        $this->assertFileExists($targetFilename);
        
        // check image
        $this->assertEquals(
            getimagesize($sourceFilename), 
            getimagesize($targetFilename)
        );
    }
    
    public function testWrite_Png()
    {
        $sourceFilename = __DIR__ . '/test.png';
        $targetFilename = sys_get_temp_dir() . '/sokil-php-image.png';
        
        $image = $this->factory->openImage($sourceFilename);
        $this->factory->writeImage(
            $image,
            'png', 
            function(PngWriteStrategy $writeStrategy) use($targetFilename) {
                $writeStrategy->setQuality(9)->toFile($targetFilename);
            }
        );
            
        // check file existance
        $this->assertFileExists($targetFilename);
        
        // check image
        $this->assertEquals(
            getimagesize($sourceFilename), 
            getimagesize($targetFilename)
        );
    }
    
    public function testResize()
    {
        $image = $this->factory->openImage(__DIR__ . '/test.png');
        $resizedImage = $this->factory->resizeImage($image, 'scale', 100, 200);
        
        $this->assertEquals(100, $resizedImage->getWidth());
        $this->assertEquals(66, $resizedImage->getHeight());
    }
    
    public function testRotate()
    {
        $image = $this->factory->openImage(__DIR__ . '/test.png');
        $rotatedImage = $image->rotate(90, '#FF0000');
        
        $this->assertEquals(200, $rotatedImage->getWidth());
        $this->assertEquals(300, $rotatedImage->getHeight());
    }

    public function flipDataProvider()
    {
        return array(
            'vertical' => array('flipVertical', array(50, 50), array(50, 150)),
            'horizontal' => array('flipHorizontal', array(50, 100), array(250, 100)),
            'both_vertical' => array('flipBoth', array(50, 50), array(50, 150)),
            'both_horizontal' => array('flipBoth', array(50, 100), array(250, 100)),
        );
    }

    /**
     * @dataProvider flipDataProvider
     */
    public function testFlip($methodName, $point, $flippedPoint)
    {
        // load image
        $image = $this->factory->openImage(__DIR__ . '/test.png');

        // get color
        $expectedColor = imagecolorsforindex(
            $image->getResource(),
            imagecolorat($image->getResource(), $point[0], $point[1])
        );

        // flip
        $reflection = new \ReflectionClass($image);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        $flippedImage = $method->invoke($image);

        // get flipped color
        $actualColor = imagecolorsforindex(
            $flippedImage->getResource(),
            imagecolorat($flippedImage->getResource(), $flippedPoint[0], $flippedPoint[1])
        );

        // check
        $this->assertEquals($expectedColor, $actualColor);
    }
    
    public function testGreyscale()
    {
        $image = $this->factory->openImage(__DIR__ . '/test.png');
        $filteredImage = $this->factory->filterImage($image, 'greyscale');
        
        $color = imagecolorat($filteredImage->getResource(), 0, 0);
        $this->assertEquals(array(29, 29, 29), Rgb::fromIntAsArray($color));
        
        $color = imagecolorat($filteredImage->getResource(), 0, 199);
        $this->assertEquals(array(225, 225, 225), Rgb::fromIntAsArray($color));
    }
    
    public function testAppendElement_TextElement()
    {        
        // text element
        $element = $this->factory
            ->createTextElement()
            ->setText('hello world')
            ->setAngle(20)
            ->setSize(40)
            ->setFont(__DIR__ . '/FreeSerif.ttf');
        
        // place text to image
        $image = $this->factory
            ->createImage(300, 300)
            ->fill(Rgb::createWhite())
            // draw shadow
            ->appendElementAtPosition($element->setColor('#ababab'), 50, 150)
            // draw text
            ->appendElementAtPosition($element->setColor('#ff0000'), 49, 149);
        
        $intColor = imagecolorat($image->getResource(), 47, 126);
        $color = Rgb::fromInt($intColor)->toArray();
        
        $this->assertEquals(array(255, 0, 0, 0), $color);
    }
    
    public function testCrop()
    {
        $image = $this->factory->openImage(__DIR__ . '/test.png');
        $croppedImage = $image->crop(10, 10, 10, 10);
        
        $this->assertEquals(10, imagesx($croppedImage->getResource()));
        $this->assertEquals(10, imagesy($croppedImage->getResource()));
        $this->assertEquals(
            array(0, 0, 255, 0),
            Rgb::fromInt(imagecolorat($croppedImage->getResource(), 5, 5))->toArray()
        );
    }
}