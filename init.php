<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * Returns an image identifier representing the image obtained from the given filename
 * @param <string> $filename
 * @return <resource> Returns an image resource identifier on success, FALSE on errors
 */
function imagecreatefrombmp($filename)
{
	// Open $filename as binary
	if( ! $handle = fopen($filename,"rb"))
	{
		return false;
	}

	// Unpack the binnary string
	$unpack_format = "vfile_type/Vfile_size/Vreserved/Vbitmap_offset";
	$file = unpack($unpack_format, fread($handle,14));

	// Make sure the filetype is bitmap
	if ($file['file_type'] != 19778)
	{
		return false;
	}

	// Unpack bitmap data
	$bmp_unpack_format = 'Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel/Vcompression/Vsize_bitmap/Vhoriz_resolution/Vvert_resolution/Vcolors_used/Vcolors_important';
	$bmp = unpack($bmp_unpack_format, fread($handle,40));

	// Calculate the number of colors
	$bmp['colors'] = pow(2,$bmp['bits_per_pixel']);
	
	// Check it size_bitmap is set
	if( $bmp['size_bitmap'] == 0 )
	{
		// Set it
		$bmp['size_bitmap'] = $file['file_size'] - $file['bitmap_offset'];
	}

	// Calculate Bytes Per Pixel
	$bmp['bytes_per_pixel'] = $bmp['bits_per_pixel']/8;

	// Calculate upper bound of Bytes Per Pixel
	$bmp['bytes_per_pixel2'] = ceil($bmp['bytes_per_pixel']);

	// Calculate Decal
	$decal = 4 - ( 4 * (( $bmp['width'] * $bmp['bytes_per_pixel'] / 4) - floor( $bmp['width'] * $bmp['bytes_per_pixel'] / 4) ) );

	if( $decal == 4 )
	{
		$bmp['decal'] = 0;
	}
	else
	{
		$bmp['decal'] = $decal;
	}

	// Load the palette
	$palette = array();
	if ($bmp['colors'] < 16777216)
	{
		$format = 'V'.$bmp['colors'];
		$length = $bmp['colors'] * 4;
		$palette = unpack( $format, fread($handle, $length) );
	}

	// Build the image
	$image = fread($handle,$bmp['size_bitmap']);
	$empty = chr(0);

	// Start with a truecolor image
	$resource = imagecreatetruecolor($bmp['width'],$bmp['height']);
	$len = 0;
	$height = $bmp['height']-1;
	
	while( $height >= 0 )
	{
		$width=0;
		while ($width < $bmp['width'])
		{
			if( $bmp['bits_per_pixel'] == 24 )
			{
				$color = unpack("V",substr($image,$len,3).$empty);
			}
			elseif( $bmp['bits_per_pixel'] == 16 )
			{
				$color = unpack("n",substr($image,$len,2));
				$color[1] = $palette[$color[1]+1];
			}
			elseif( $bmp['bits_per_pixel'] == 8 )
			{
				$color = unpack("n",$empty.substr($image,$len,1));
				$color[1] = $palette[$color[1]+1];
			}
			elseif( $bmp['bits_per_pixel'] == 4 )
			{
				$color = unpack("n",$empty.substr($image,floor($len),1));
				if (($len*2)%2 == 0) $color[1] = ($color[1] >> 4) ; else $color[1] = ($color[1] & 0x0F);
				$color[1] = $palette[$color[1]+1];
			}
			elseif( $bmp['bits_per_pixel'] == 1 )
			{
				$color = unpack("n",$empty.substr($image,floor($len),1));
				switch( ($len * 8) % 8 )
				{
					case 0:
					{
						$color[1] =  $color[1] >> 7;
					} break;
					case 1:
					{
						$color[1] = ($color[1] & 0x40) >> 6;
					} break;
					case 2:
					{
						$color[1] = ($color[1] & 0x20) >> 5;
					} break;
					case 3:
					{
						$color[1] = ($color[1] & 0x10) >> 4;
					} break;
					case 4:
					{
						$color[1] = ($color[1] & 0x8) >> 3;
					} break;
					case 5:
					{
						$color[1] = ($color[1] & 0x4) >> 2;
					} break;
					case 6:
					{
						$color[1] = ($color[1] & 0x2) >> 1;
					} break;
					case 7:
					{
						$color[1] = ($color[1] & 0x1);
					} break;
				}
				$color[1] = $palette[ $color[1] + 1 ];
			}
			else
			{
				return false;
			}

			// Set the current pixel
			imagesetpixel($resource,$width,$height,$color[1]);

			// Increment in the Width
			$width++;

			// Increment the size
			$len += $bmp['bytes_per_pixel'];
		}

		// Decrement in the Height
		$height--;

		// Increment the size
		$len += $bmp['decal'];
	}

	// Release the file
	fclose($handle);

	// Return the image resource
	return $resource;
}