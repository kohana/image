<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Image manipulation abstract class.
 *
 * @package    Image
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license.html
 */
abstract class Image {

	// Resizing contraints
	const NONE   = 0x01;
	const WIDTH  = 0x02;
	const HEIGHT = 0x03;
	const AUTO   = 0x04;

	/**
	 * @var  string  default driver: GD, ImageMagick, etc
	 */
	public static $default_driver = 'GD';

	// Status of the driver check
	protected static $_checked = FALSE;

	/**
	 * Creates an image wrapper.
	 *
	 * @param   string   image file path
	 * @param   string   driver type: GD, ImageMagick, etc
	 * @return  Image
	 */
	public static function factory($file, $driver = NULL)
	{
		if ($driver === NULL)
		{
			// Use the default driver
			$driver = Image::$default_driver;
		}

		// Set the class name
		$class = 'Image_'.$driver;

		return new $class($file);
	}

	/**
	 * @var  string  image file path
	 */
	public $file;

	/**
	 * @var  integer  image width
	 */
	public $width;

	/**
	 * @var  integer  image height
	 */
	public $height;

	/**
	 * @var  integer  one of the IMAGETYPE_* constants
	 */
	public $type;

	/**
	 * Loads information about the image.
	 *
	 * @throws  Kohana_Exception
	 * @param   string   image file path
	 * @return  void
	 */
	public function __construct($file)
	{
		try
		{
			// Get the real path to the file
			$file = realpath($file);

			// Get the image information
			$info = getimagesize($file);
		}
		catch (Exception $e)
		{
			// Ignore all errors while reading the image
		}

		if (empty($file) OR empty($info))
		{
			throw new Kohana_Exception('Not an image or invalid image: :file',
				array(':file' => Kohana::debug_path($file)));
		}

		// Store the image information
		$this->file   = $file;
		$this->width  = $info[0];
		$this->height = $info[1];
		$this->type   = $info[2];
	}

	/**
	 * Resize the image to the given size. Either the width or the height can
	 * be omitted and the image will be resized proportionally.
	 *
	 * @param   integer  new width
	 * @param   integer  new height
	 * @param   integer  master dimension
	 * @return  $this
	 */
	public function resize($width = NULL, $height = NULL, $master = NULL)
	{
		if (empty($width))
		{
			if ($master === Image::NONE)
			{
				// Use the current width
				$width = $this->width;
			}
			else
			{
				// Recalculate the width based on the height proportions
				// This must be done before the automatic master check
				$width = $this->width * $height / $this->height;
			}
		}

		if (empty($height))
		{
			if ($master === Image::NONE)
			{
				// Use the current height
				$height = $this->height;
			}
			else
			{
				// Recalculate the height based on the width
				// This must be done before the automatic master check
				$height = $this->height * $width / $this->width;
			}
		}

		if ($master === NULL OR $master === Image::AUTO)
		{
			// Reset the master dim to the correct direction
			$master = ($this->width / $width) > ($this->height / $height) ? Image::WIDTH : Image::HEIGHT;
		}

		switch ($master)
		{
			case Image::WIDTH:
				// Proportionally set the height
				$height = $this->height * $width / $this->width;
			break;
			case Image::HEIGHT:
				// Proportionally set the width
				$width = $this->width * $height / $this->height;
			break;
		}

		// Convert the width and height to integers
		$width  = (int) $width;
		$height = (int) $height;

		$this->_do_resize($width, $height);

		return $this;
	}

	/**
	 * Crop an image to the given size. Either the width or the height can be
	 * omitted and the current width or height will be used.
	 *
	 * If no offset is specified, the center of the axis will be used.
	 *
	 * If an offset of -1 is specified, the bottom of the axis will be used.
	 *
	 * @param   integer  new width
	 * @param   integer  new height
	 * @param   mixed    offset from the left
	 * @param   mixed    offset from the top
	 * @return  $this
	 */
	public function crop($width = NULL, $height = NULL, $offset_x = NULL, $offset_y = NULL)
	{
		if ($width === NULL)
		{
			// Use the current width
			$width = $this->width;
		}

		if ($height === NULL)
		{
			// Use the current height
			$height = $this->height;
		}

		// Convert the width and height to integers
		$width  = (int) $width;
		$height = (int) $height;

		if ($offset_x === NULL)
		{
			// Center the X offset
			$offset_x = (int) ($this->width - $width) / 2;
		}
		elseif ($offset_x === -1)
		{
			// Bottom the X offset
			$offset_x = (int) ($this->width - $width);
		}

		if ($offset_y === NULL)
		{
			// Center the Y offset
			$offset_y = (int) ($this->height - $height) / 2;
		}
		elseif ($offset_y === -1)
		{
			// Bottom the Y offset
			$offset_y = (int) ($this->height - $height);
		}

		$this->_do_crop($width, $height, $offset_x, $offset_y);

		return $this;
	}

	/**
	 * Rotate the image.
	 *
	 * @param   integer   degrees to rotate: -360-360
	 * @return  $this
	 */
	public function rotate($degrees)
	{
		// Make the degrees an integer
		$degrees = (int) $degrees;

		if ($degrees > 180)
		{
			do
			{
				// Keep subtracting full circles until the degrees have normalized
				$degrees -= 360;
			}
			while($degrees > 180);
		}

		if ($degrees < -180)
		{
			do
			{
				// Keep adding full circles until the degrees have normalized
				$degrees += 360;
			}
			while($degrees < -180);
		}

		$this->_do_rotate($degrees);

		return $this;
	}

	/**
	 * Sharpen the image.
	 *
	 * @param   integer  amount to sharpen: 1-100
	 * @return  $this
	 */
	public function sharpen($amount)
	{
		// The amount must be in the range of 1 to 100
		$amount = min(max($amount, 1), 100);

		$this->_do_sharpen($amount);

		return $this;
	}

	/**
	 * Add a watermark to an image with a specified opacity.
	 *
	 * If no offset is specified, the center of the axis will be used.
	 *
	 * If an offset of -1 is specified, the bottom of the axis will be used.
	 *
	 * @param   object   watermark Image instance
	 * @param   integer  offset from the left
	 * @param   integer  offset from the top
	 * @param   integer  opacity of watermark
	 * @return  $this
	 */
	public function watermark(Image $watermark, $offset_x = NULL, $offset_y = NULL, $opacity = 100)
	{
		if ($offset_x === NULL)
		{
			// Center the X offset
			$offset_x = (int) ($this->width - $watermark->width) / 2;
		}
		elseif ($offset_x === -1)
		{
			// Bottom the X offset
			$offset_x = (int) ($this->width - $watermark->width);
		}

		if ($offset_y === NULL)
		{
			// Center the Y offset
			$offset_y = (int) ($this->height - $watermark->height) / 2;
		}
		elseif ($offset_y === -1)
		{
			// Bottom the Y offset
			$offset_y = (int) ($this->height - $watermark->height);
		}

		// The opacity must be in the range of 1 to 100
		$opacity = min(max($opacity, 1), 100);

		$this->_do_watermark($watermark, $offset_x, $offset_y, $opacity);

		return $this;
	}

	/**
	 * Save the image. If the filename is omitted, the original image will
	 * be overwritten.
	 *
	 * @param   string   new image path
	 * @param   integer  quality of image: 1-100
	 * @return  boolean
	 */
	public function save($file = NULL, $quality = 100)
	{
		if ($file === NULL)
		{
			// Overwrite the file
			$file = $this->file;
		}

		// Get the directory of the file
		$directory = realpath(pathinfo($file, PATHINFO_DIRNAME));

		if ( ! is_dir($directory) OR ! is_writable($directory))
		{
			throw new Kohana_Exception('Directory must be writable: :directory',
				array(':directory' => Kohana::debug_path($directory)));
		}

		return $this->_do_save($file, $quality);
	}

	/**
	 * Render the image and return the data.
	 *
	 * @param   string   image type to return: png, jpg, gif, etc
	 * @param   integer  quality of image: 1-100
	 * @return  string
	 */
	public function render($type = NULL, $quality = 100)
	{
		if ($type === NULL)
		{
			// Use the current image type
			$type = image_type_to_extension($this->type, FALSE);
		}

		return $this->_do_render($type, $quality);
	}

	/**
	 * Execute a resize.
	 *
	 * @param   integer  new width
	 * @param   integer  new height
	 * @return  void
	 */
	abstract protected function _do_resize($width, $height);

	/**
	 * Execute a crop.
	 *
	 * @param   integer  new width
	 * @param   integer  new height
	 * @param   integer  offset from the left
	 * @param   integer  offset from the top
	 * @return  void
	 */
	abstract protected function _do_crop($width, $height, $offset_x, $offset_y);

	/**
	 * Execute a rotation.
	 *
	 * @param   integer  degrees to rotate
	 * @return  void
	 */
	abstract protected function _do_rotate($degrees);

	/**
	 * Execute a sharpen.
	 *
	 * @param   integer  amount to sharpen
	 * @return  void
	 */
	abstract protected function _do_sharpen($amount);

	/**
	 * Execute a watermarking.
	 *
	 * @param   object   watermarking Image
	 * @param   integer  offset from the left
	 * @param   integer  offset from the top
	 * @param   integer  opacity of watermark
	 * @return  void
	 */
	abstract protected function _do_watermark(Image $image, $offset_x, $offset_y, $opacity);

	/**
	 * Execute a save.
	 *
	 * @param   string   new image filename
	 * @param   integer  quality
	 * @return  boolean
	 */
	abstract protected function _do_save($file, $quality);

	/**
	 * Execute a render.
	 *
	 * @param   string    image type: png, jpg, gif, etc
	 * @param   integer   quality
	 * @return  string
	 */
	abstract protected function _do_render($type, $quality);

} // End Image
