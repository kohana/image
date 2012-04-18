<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Support for image manipulation using [Imagick](http://php.net/Imagick).
 *
 * @package    Kohana/Image
 * @category   Drivers
 * @author     Tamas Mihalik tamas.mihalik@gmail.com
 * @copyright  (c) 2009-2010 Kohana Team
 * @license    http://kohanaphp.com/license.html
 */
class Kohana_Image_ImageMagick extends Image {

	/**
	 * @var  Imagick
	 */
	protected $im;

	/**
	 * Checks if ImageMagick is enabled.
	 *
	 * @throws  Kohana_Exception
	 * @return  boolean
	 */
	public static function check()
	{
		if (!extension_loaded('imagick')) 
		{
			throw new Kohana_Exception('ImageMagick is not installed, check your configuration');
		}

		return Image_ImageMagick::$_checked = TRUE;
	}

	/**
	 * Runs [Image_ImageMagick::check] and loads the image.
	 *
	 * @return  void
	 * @throws  Kohana_Exception
	 */
	public function __construct($file)
	{
		if ( ! Image_ImageMagick::$_checked)
		{
			// Run the install check
			Image_ImageMagick::check();
		}

		// Some ImageMagick methods won't work without this
		setlocale(LC_ALL, 'en_US.utf-8');

		parent::__construct($file);
		
		$this->im = new Imagick;
		$this->im->readImage($file);
	}

	/**
	 * Destroys the loaded image to free up resources.
	 *
	 * @return  void
	 */
	public function __destruct()
	{
		$this->im->clear();
		$this->im->destroy();
	}

	protected function _do_resize($width, $height)
	{
		if ($this->im->resizeImage($width, $height, Imagick::FILTER_CUBIC, 0.5)) 
		{
			// Reset the width and height
			$this->width = $this->im->getImageWidth();
			$this->height = $this->im->getImageHeight();
			
			return TRUE;
		}
		
		return FALSE;
	}

	protected function _do_crop($width, $height, $offset_x, $offset_y)
	{
		if ($this->im->cropImage($width, $height, $offset_x, $offset_y))
		{
			// Reset the width and height
			$this->width = $this->im->getImageWidth();
			$this->height = $this->im->getImageHeight();
			
			return TRUE;
		}
		
		return FALSE;
	}

	protected function _do_rotate($degrees) 
	{
		if ($this->im->rotateImage(new ImagickPixel, $degrees))
		{
			// Reset the width and height
			$this->width = $this->im->getImageWidth();
			$this->height = $this->im->getImageHeight();

			return TRUE;
		}
		
		return FALSE;
	}

	protected function _do_flip($direction)
	{
		if ($direction === Image::HORIZONTAL)
		{
			$this->im->flopImage();
		} 
		else
		{
			$this->im->flipImage();
		}
		
		
		// Reset the width and height
		$this->width = $this->im->getImageWidth();
		$this->height = $this->im->getImageHeight();
	}

	protected function _do_sharpen($amount) 
	{
		//IM not support $amount under 5 (0.15)
		$amount = ($amount < 5) ? 5 : $amount;
		
		// Amount should be in the range of 0.0 to 3.0
		$amount = ($amount * 3.0) / 100;
		
		if ($this->im->sharpenImage(0, $amount))
		{
			// Reset the width and height
			$this->width = $this->im->getImageWidth();
			$this->height = $this->im->getImageHeight();
			
			return TRUE;
		}
		
		return FALSE;
	}
	
	protected function _do_reflection($height, $opacity, $fade_in)
	{
		// TODO
	}

	protected function _do_watermark(Image $image, $offset_x, $offset_y, $opacity) 
	{
		$this->im->compositeImage($image->im, Imagick::COMPOSITE_DISSOLVE, $offset_x, $offset_y);
	}

	protected function _do_background($r, $g, $b, $opacity)
	{
		$opacity = $opacity / 100;

		// TODO
	}

	protected function _do_save($file, $quality)
	{
		// Get the extension of the file
		$extension = pathinfo($file, PATHINFO_EXTENSION);
		
		// Get the save function and IMAGETYPE
		$type = $this->_save_function($extension, $quality);
		
		$this->im->setImageCompressionQuality($quality);

		if ($this->im->writeImage($file)) 
		{
			// Reset the image type and mime type
			$this->type = $type;
			$this->mime = image_type_to_mime_type($type);
			
			return TRUE;
		}
		
		return FALSE;
	}

	protected function _do_render($type, $quality)
	{
		// Get the save function and IMAGETYPE
		$type = $this->_save_function($type, $quality);
		
		$this->im->setImageCompressionQuality($quality);
		
		// Reset the image type and mime type
		$this->type = $type;
		$this->mime = image_type_to_mime_type($type);

		return $this->im->__toString();
	}
	
	/**
	 * Get the image type for this extension.
	 * Also normalizes the quality setting
	 *
	 * @param   string   image type: png, jpg, etc
	 * @param   integer  image quality
	 * @return  string   IMAGETYPE_* constant
	 * @throws  Kohana_Exception
	 */
	protected function _save_function($extension, & $quality)
	{
		switch (strtolower($extension))
		{
			case 'jpg':
			case 'jpeg':
				$type = IMAGETYPE_JPEG;
				$this->im->setImageFormat('jpeg');
			break;
			case 'gif':
				$type = IMAGETYPE_GIF;
				$this->im->setImageFormat('gif');
			break;
			case 'png':
				$type = IMAGETYPE_PNG;
				$this->im->setImageFormat('png');
			break;
			default:
				throw new Kohana_Exception('Installed ImageMagick does not support :type images',
					array(':type' => $extension));
			break;
		}
		
		$quality = $quality - 5;

		return $type;
	}
} // End Kohana_Image_ImageMagick