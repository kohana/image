<?php defined('SYSPATH') or die('No direct script access.');

abstract class Image {

	const NONE      = 0x01;
	const WIDTH     = 0x02;
	const HEIGHT    = 0x03;
	const AUTO      = 0x04;

	public static $default_type = 'GD';

	/**
	 * Creates an image wrapper.
	 *
	 * @param   string   image file path
	 * @param   string   driver type: GD, ImageMagick, etc
	 * @return  Image
	 */
	public static function factory($file, $type = NULL)
	{
		if ($type === NULL)
		{
			// Use the default type
			$type = Image::$default_type;
		}

		// Set the class name
		$class = 'Image_'.$type;

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
	 * omitted and the current width or height will be used. Use TRUE for an
	 * offset to center the offset. Use -1 for an offset to crop from the
	 * bottom/right.
	 *
	 * @param   integer  new width
	 * @param   integer  new height
	 * @param   integer  offset from the left
	 * @param   integer  offset from the top
	 * @return  $this
	 */
	public function crop($width = NULL, $height = NULL, $offset_x = TRUE, $offset_y = TRUE)
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

		if ($offset_x === TRUE)
		{
			// Center the X offset
			$offset_x = (int) ($this->width - $width) / 2;
		}
		elseif ($offset_x === -1)
		{
			// Bottom the X offset
			$offset_x = (int) ($this->width - $width);
		}

		if ($offset_y = TRUE)
		{
			// Center the Y offset
			$offset_y = (int) ($this->height - $height) / 2;
		}
		elseif ($offset_y === -1)
		{
			// Bottom the Y offset
			$offset_y = (int) ($this->width - $width);
		}

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
		$this->_do_sharpen((int) $amount);

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

	abstract protected function _do_resize($width, $height);

	abstract protected function _do_crop($width, $height, $offset_x, $offset_y);

	abstract protected function _do_rotate($degrees);

	abstract protected function _do_sharpen($amount);

	abstract protected function _do_save($file, $quality);

} // End Image
