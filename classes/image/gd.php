<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Image manipulation class using {@link  http://php.net/gd GD}.
 *
 * @package    Image
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license.html
 */
class Image_GD extends Image {

	public static function check()
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

		return Image_GD::$_checked = TRUE;
	}

	// Temporary image resource
	protected $_image;

	public function __construct($file)
	{
		if ( ! Image_GD::$_checked)
		{
			// Run the install check
			Image_GD::check();
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

		// Preserve transparency when saving
		imagesavealpha($this->_image, TRUE);
	}

	public function __destruct()
	{
		if (is_resource($this->_image))
		{
			// Free all resources
			imagedestroy($this->_image);
		}
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
			$reduction_width  = round($width  * 1.1);
			$reduction_height = round($height * 1.1);

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
		if (imagecopyresampled($image, $this->_image, 0, 0, $offset_x, $offset_y, $width, $height, $width, $height))
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

		// Yes, imagerotate() returns an image resource...
		// PHP + consistency = (divide by zero error)
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

	protected function _do_watermark(Image $watermark, $offset_x, $offset_y, $opacity)
	{
		// Create the watermark image resource
		$overlay = imagecreatefromstring($watermark->render());

		// Get the width and height of the watermark
		$width  = imagesx($overlay);
		$height = imagesy($overlay);

		// Prevent the alpha from being lost
		imagealphablending($this->_image, TRUE);

		if (imagecopy($this->_image, $overlay, $offset_x, $offset_y, 0, 0, $width, $height))
		{
			// Destroy the overlay image
			imagedestroy($overlay);
		}
	}

	protected function _do_save($file, $quality)
	{
		// Get the extension of the file
		$type = pathinfo($file, PATHINFO_EXTENSION);

		switch ($type)
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
					array(':type' => $type));
			break;
		}

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

	protected function _do_render($type, $quality)
	{
		switch ($type)
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
					array(':type' => $type));
			break;
		}

		// Capture the output
		ob_start();

		if (isset($quality))
		{
			// Save the image with a quality setting
			$save($this->_image, NULL, $quality);
		}
		else
		{
			// Save the image with no quality
			$save($this->_image, NULL);
		}

		return ob_get_clean();
	}

	protected function _create($width, $height)
	{
		// Create an empty image
		$image = imagecreatetruecolor($width, $height);

		// Do not apply alpha blending
		imagealphablending($image, FALSE);

		// Save alpha levels
		imagesavealpha($image, TRUE);

		return $image;
	}

} // End Image_GD
