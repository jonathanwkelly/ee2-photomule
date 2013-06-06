ee2-photomule
=============

# Description #
The Photomule Plugin will generate an image - optimized, resized, cropped - wrapped in an image tag. The impetus behind the plugin was that the file upload would be of a high-resolution image, then this plugin be used to produce the optimized web-version of various file sizes and dimensions. 


# Requirements #
* GD Library
* Writable upload directories (a child directory will be created to house the generated images; see "CACHING" below)


# Parameters #

`image="{image_customfield_name}"`
*The raw file to be processed*

`default_image="/path/to/default/image.jpg"`
*If something happens during image processing, this image will be returned. (No processing / sizing will be done to this image -- it will be output "as-is".)*

`width="120"`
*Target output width*

`height="120"`
*Target output height*

`fill="yes"`
*Setting this parameter will ensure that the produced image is greater than or equal to both dimensions. (Both "width" and "height" parameters must be set for this to take effect.)*

`negative_margin="yes"`
*Setting this parameter will cause the resulting image tag to have a negative top or left margin, if there is an overflow of the scaled image. This only applies to images that use the fill="yes" option. (NOTE: you will likely need to have an parent element that has uses overflow:hidden if you set this option.)*

`scale_up="yes"`
*Allows for an image to be scaled up to meet the dimensional requirements set by "width" and "height". (Both "width" and "height" parameters must be set for this to take effect.)*

`id="someid"`
*The output img tag will have this id*

`class="someclass"`
*The output img tag will have these classes*


# EXAMPLE 1: Scale down and fill dimensions #
By sizing the image to fit the 600px target height, it can be scaled according to its original aspect ratio and fill the target dimensions. This caused the width to "overflow" the target 800px width. This overflow amount is halved and set as a negative left margin to make up for the overflow. 

`{exp:photomule 
	image="image-1200w-x-800h.jpg" 
	width="800"
	height="600"
	fill="yes" 
	negative_margin="yes"
}`

Will output:

`<img src="/new/generated/image.jpg" style="width: 1067px; height: 600px; margin-left: -67px;">`


# EXAMPLE 2: Scale down and constrain to a width #
By passing in just a width (or just a height), the image will be scaled, according to its original aspect ratio, to meet that dimension. The the second dimension will be resized to scale.

`{exp:photomule 
	image="image-1200w-x-800h.jpg" 
	width="450"
}`

Will output:

`<img src="/new/generated/image.jpg" style="width: 450px; height: 300px;">`


# Caching #
Once an image has been generated, it is stored as a new file. The modified time for the original image being resized is considered when evaluating if we have a valid cached version. If the original image is newer than its cached counterpart, then the resized image will be re-generated and re-cached.

Cached images are stored within the same upload directory as the original image, in a child _pm-cache directory.


# Troubleshooting #
If you're not getting the expected results, ensure that your PHP configuration has an error_log path configured. Any errors that occurs while Photo Mule is doin' its thang will be logged using PHP's native error_log() function.