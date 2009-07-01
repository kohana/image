<?php defined('SYSPATH') or die('No direct script access.');

class Image_GD extends Image {

	// Is the GD installation usable?
	protected static $_checked = FALSE;

	// Base64 encoded 1x1 transparent PNG
	protected static $_blank_png =  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII=';

	/**
	 * Checks that GD is installed and at least version 2.0.
	 *
	 * @throws  Kohana_Exception
	 * @return  void
	 */
	public static function check_install()
	{
		if ( ! function_exists('gd_info'))
		{
			throw new Kohana_Exception('GD is either not installed or not enabled, check your configuration');
		}

		if (defined('GD_VERSION'))
		{
			// Get the version via a constant, available in PHP 5.2.4+
			$version = GD_VERSION;
		}
		else
		{
			// Get the version information
			$version = current(gd_info());

			// Extract the version number
			preg_match('/\d+\.\d+(?:\.\d+)?/', $version, $matches);

			// Get the major version
			$version = $matches[0];
		}

		if ( ! version_compare($version, '2.0', '>='))
		{
			throw new Kohana_Exception('Image_GD requires GD version 2.0 or greater, you have :version',
				array(':version' => $version));
		}

		// Installed GD is acceptable
		Image_GD::$_checked = TRUE;
	}

	// Temporary image resource
	protected $_image;

	public function __construct($file)
	{
		if ( ! Image_GD::$_checked)
		{
			// Run the install check
			Image_GD::check_install();
		}

		parent::__construct($file);

		// Set the image creation function name
		switch ($this->type)
		{
			case IMAGETYPE_JPEG:
				$create = 'imagecreatefromjpeg';
			break;
			case IMAGETYPE_GIF:
				$create = 'imagecreatefromgif';
			break;
			case IMAGETYPE_PNG:
				$create = 'imagecreatefrompng';
			break;
		}

		if ( ! isset($create) OR ! function_exists($create))
		{
			throw new Kohana_Exception('Installed GD does not support :type images',
				array(':type' => image_type_to_extension($this->type, FALSE)));
		}

		// Open the temporary image
		$this->_image = $create($this->file);

		// Prevent the alpha from being lost
		imagealphablending($this->_image, TRUE);
		imagesavealpha($this->_image, TRUE);
	}

	protected function _do_resize($width, $height)
	{
		// Presize width and height
		$pre_width = $this->width;
		$pre_height = $this->height;

		// Test if we can do a resize without resampling to speed up the final resize
		if ($width > ($this->width / 2) AND $height > ($this->height / 2))
		{
			// The maximum reduction is 10% greater than the final size
			$reduction_width  = (int) ($width  * 1.1);
			$reduction_height = (int) ($height * 1.1);

			while ($pre_width / 2 > $reduction_width AND $pre_height / 2 > $reduction_height)
			{
				// Reduce the size using an O(2n) algorithm, until it reaches the maximum reduction
				$pre_width /= 2;
				$pre_height /= 2;
			}

			// Create the temporary image to copy to
			$image = $this->_create($pre_width, $pre_height);

			if (imagecopyresized($image, $this->_image, 0, 0, 0, 0, $pre_width, $pre_height, $this->width, $this->height))
			{
				// Swap the new image for the old one
				imagedestroy($this->_image);
				$this->_image = $image;
			}
		}

		// Create the temporary image to copy to
		$image = $this->_create($width, $height);

		// Execute the resize
		if (imagecopyresampled($image, $this->_image, 0, 0, 0, 0, $width, $height, $pre_width, $pre_height))
		{
			// Swap the new image for the old one
			imagedestroy($this->_image);
			$this->_image = $image;

			// Reset the width and height
			$this->width  = imagesx($image);
			$this->height = imagesy($image);
		}
	}

	protected function _do_crop($width, $height, $offset_x, $offset_y)
	{
		// Create the temporary image to copy to
		$image = $this->_create($width, $height);

		// Execute the crop
		if (imagecopyresampled($image, $this->_image, 0, 0, $offset_x, $offset_y, $width, $height, $this->width, $this->height))
		{
			// Swap the new image for the old one
			imagedestroy($this->_image);
			$this->_image = $image;

			// Reset the width and height
			$this->width  = imagesx($image);
			$this->height = imagesy($image);
		}
	}

	protected function _do_sharpen($amount)
	{
		// Amount should be in the range of 18-10
		$amount = round(abs(-18 + ($amount * 0.08)), 2);

		// Gaussian blur matrix
		$matrix = array
		(
			array(-1,   -1,    -1),
			array(-1, $amount, -1),
			array(-1,   -1,    -1),
		);

		// Perform the sharpen
		if ($image = imageconvolution($this->_image, $matrix, $amount - 8, 0))
		{
			// Swap the new image for the old one
			imagedestroy($this->_image);
			$this->_image = $image;

			// Reset the width and height
			$this->width  = imagesx($image);
			$this->height = imagesy($image);
		}
	}

	protected function _do_rotate($degrees)
	{
		// White, with an alpha of 0
		$transparent = imagecolorallocatealpha($this->_image, 255, 255, 255, 127);

		if ($image = imagerotate($this->_image, 360 - $degrees, $transparent))
		{
			// Swap the new image for the old one
			imagedestroy($this->_image);
			$this->_image = $image;

			// Reset the width and height
			$this->width  = imagesx($image);
			$this->height = imagesy($image);
		}
	}

	protected function _do_save($file, $quality)
	{
		// Get the extension of the file
		$extension = pathinfo($file, PATHINFO_EXTENSION);

		switch ($extension)
		{
			case 'jpg':
			case 'jpeg':
				// Save a JPG file
				$save = 'imagejpeg';
			break;
			case 'gif':
				// GIFs do not a quality setting
				unset($quality);

				// Save a GIF file
				$save = 'imagegif';
			break;
			case 'png':
				// Use a compression level of 9
				// Note that compression is not the same as quality!
				$quality = 9;

				// Save a PNG file
				$save = 'imagepng';
			break;
			default:
				throw new Kohana_Exception('Installed GD does not support :type images',
					array(':type' => $extension));
			break;
		}

		// Prevent the alpha from being lost
		imagealphablending($this->_image, TRUE);
		imagesavealpha($this->_image, TRUE);

		if (isset($quality))
		{
			// Save the image with a quality setting
			return $save($this->_image, $file, $quality);
		}
		else
		{
			// Save the image with no quality
			return $save($this->_image, $file);
		}
	}

	protected function _create($width, $height)
	{
		if (is_string(self::$_blank_png))
		{
			// Decode and create the blank PNG
			self::$_blank_png = imagecreatefromstring(base64_decode(self::$_blank_png));
		}

		// Create an empty image
		$image = imagecreatetruecolor($width, $height);

		// Resize the blank image
		imagecopyresized($image, self::$_blank_png, 0, 0, 0, 0, $width, $height, 1, 1);

		// Prevent the alpha from being lost
		imagealphablending($image, FALSE);
		imagesavealpha($image, TRUE);

		return $image;
	}

} // End Image_GD
