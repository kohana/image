# Basic Usage

Shown here are the basic usage of this module. For full documentation about the image module usage, visit the [Image] api browser.

## Creating Instance

[Image::factory()] creates an instance of the image object and prepares it for manipulation. It accepts the `filename` as an arguement and an optional `driver` parameter. When `driver` is not specified, the default driver `GD` is used.

~~~
// Uses the image from upload directory
$img = Image::factory(DOCROOT.'uploads/sample-image.jpg');
~~~

Once an instance is created, you can now manipulate the image by using the following instance methods.

## Resize

Resize the image to the given size. Either the width or the height can be omitted and the image will be resized proportionally.
