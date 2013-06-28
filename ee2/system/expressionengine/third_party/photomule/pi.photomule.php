<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Photomule Class
 *
 * @package		ExpressionEngine
 * @category	Plugin
 * @author 		Jonathan W. Kelly
 * @link 		http://github.com/jonathanwkelly/ee2-photomule
 */

$plugin_info = array(
	'pi_name'         => 'Photo Mule',
	'pi_version'      => '1.0',
	'pi_author'       => 'Jonathan W. Kelly',
	'pi_author_url'   => 'http://github.com/jonathanwkelly/ee2-photomule',
    'pi_description'  => 'Generates optimized, lower-resolution versions of high-resolution images.',
    'pi_usage'        => Photomule::usage()
);

class Photomule
{
    public $return_data = "";

    private $EE;
    private $params = array(
    	'image'				=> null,
    	'default_image'		=> null,
    	'width'				=> 0,
    	'height'			=> 0,
    	'fill'				=> FALSE,
    	'negative_margin'	=> FALSE,
    	'scale_up'			=> FALSE,
    	'class'				=> null,
    	'id'				=> null
    );
    private $cachedir_name = '_pm-cache';
    private $img_attrs;
    private $path_info;
    private $imgsize;
    private $upload_pref;

    // --------------------------------------------------------------------

    /**
     * @return string
     */
    public function __construct()
    {
    	$this->EE = ee();

    	/* set our params to variables */
    	foreach($this->params as $param => $default)
    	{
    		$this->params[$param] = $this->EE->TMPL->fetch_param($param, $default);
    	}

    	/* figure out where this image is on the server */
    	if(!$this->get_imgdata())
    		return;

    	/* no image was found; just return the default image */
    	if(!$this->params['image'])
    		$this->img_attrs['src'] = $this->params['default_image'];

    	/* ensure we can write out the image to a directory */
    	if(!$this->ensure_cachedir())
    		return;

    	/* calculate dimensions */
    	$this->set_img_dims();

    	/* get/rebuild cached image */
    	if(!$this->get_or_build_cached_img())
    		return;

    	return $this->build_img_tag();
    }

    // --------------------------------------------------------------------

    /**
     * To be used for producing and embedding an image for an <img> tag.
     * This is good for getting the photomule behavior from outside of
     * the exp:channel:entries looop.
     * @return {string}
     */
    public function imgtag_url()
    {
        exit('got here');
    }

    // --------------------------------------------------------------------

    /**
     * @return boolean
     */
    private function get_imgdata()
    {
    	if(empty($this->params['image']))
    		return $this->_err("I was asked to get the path info for a null image path. ");

    	$this->path_info = pathinfo($this->params['image']);

    	$this->upload_pref = $this->EE->db->like('url', $this->path_info['dirname'], 'after')->get('upload_prefs')->row();
    	if(!is_object($this->upload_pref))
    		return $this->_err("I could not find the upload preferences for the path {$this->path_info['dirname']}.");

    	$gis_path = $this->upload_pref->server_path.$this->path_info['basename'];
    	$this->imgsize = getimagesize($gis_path);
    	if(!is_array($this->imgsize))
    		return $this->_err("I could not get the image dimensions for the path {$gis_path}.");

    	return TRUE;
    }

    // --------------------------------------------------------------------

    /**
     * @return boolean
     */
    private function set_img_dims()
    {
    	/* just scale according to one dimension */
    	if(
    		($this->params['height'] && !$this->params['width']) ||
    		($this->params['width'] && !$this->params['height'])
    	)
    	{
    		if($this->params['height'])
    		{
    			$first = 'height';
    			$firstkey = 1;
    			$last = 'width';
    			$lastkey = 0;
    		}
    		elseif($this->params['width'])
    		{
    			$first = 'width';
    			$firstkey = 0;
    			$last = 'height';
    			$lastkey = 1;
    		}
    	}

    	/* scale according to both width and height */
    	elseif($this->params['width'] && $this->params['height'])
    	{
    		/* figure out the original-to-target ratios */
    		$w2w_ratio = ($this->params['width'] / $this->imgsize[0]);
    		$h2h_ratio = ($this->params['height'] / $this->imgsize[1]);

    		/* which do we scale by first? */
    		if($this->params['fill'] == 'yes')
    		{
    			if($w2w_ratio < $h2h_ratio)
    			{
    				$first = 'height';
    				$firstkey = 1;
    				$last = 'width';
    				$lastkey = 0;
    			}
    			else{
    				$first = 'width';
    				$firstkey = 0;
    				$last = 'height';
    				$lastkey = 1;
    			}
    		}
    		else
    		{
    			if($w2w_ratio < $h2h_ratio)
    			{
    				$first = 'width';
    				$firstkey = 0;
    				$last = 'height';
    				$lastkey = 1;
    			}
    			else{
    				$first = 'height';
    				$firstkey = 1;
    				$last = 'width';
    				$lastkey = 0;
    			}
    		}
    	}

    	$this->img_attrs[$first] = $this->params[$first];
    	$this->img_attrs[$last] = floor((($this->params[$first] / $this->imgsize[$firstkey]) * $this->imgsize[$lastkey]));

    	return;
    }

    // --------------------------------------------------------------------

    /**
     * @return boolean
     */
    private function get_or_build_cached_img()
    {
    	$cachedir = $this->upload_pref->server_path.$this->cachedir_name.'/';

    	/* build the cached filename */
    	$cached_filename =
    		preg_replace(
    			'/[^0-9a-z\-\_\.]/',
    			'',
    			strtolower(
    				implode(
    					'-',
    					array(
    						$this->path_info['filename'],
    						'w_'.$this->img_attrs['width'],
    						'h_'.$this->img_attrs['height'],
    						'fill_'.(($this->params['fill'] == 'yes') ? 'yes' : 'no')
    					)
    				)
    			)
    		)
    		.'.'.$this->path_info['extension'];

    	/* should we rebuild the cached image? */
    	$rawimg_serverpath = $this->upload_pref->server_path.$this->path_info['basename'];
    	if(
    		!file_exists($cachedir.$cached_filename) ||
    		(filemtime($cachedir.$cached_filename) < filemtime($rawimg_serverpath))
    	)
    	{
    		/* make a new image */
 			switch($this->path_info['extension'])
 			{
 				case 'jpg':
 				case 'jpeg':
 					$src = imagecreatefromjpeg($rawimg_serverpath);
 					break;
 				case 'png':
 					$src = imagecreatefrompng($rawimg_serverpath);
 					break;
 				case 'gif':
 					$src = imagecreatefromgif($rawimg_serverpath);
 					break;
 				default:
 					return $this->_err('Unsupported image type: '.$this->path_info['extension']);
			}

			$tmp = imagecreatetruecolor($this->img_attrs['width'], $this->img_attrs['height']);

			imagecopyresampled($tmp, $src, 0, 0, 0, 0, $this->img_attrs['width'], $this->img_attrs['height'], $this->imgsize[0], $this->imgsize[1]);

			imagejpeg($tmp, $cachedir.$cached_filename, 80);

			imagedestroy($src);
			imagedestroy($tmp);
    	}

    	$this->img_attrs['src'] = rtrim($this->upload_pref->url, '/').'/'.$this->cachedir_name.'/'.$cached_filename;

    	return TRUE;
    }

    // --------------------------------------------------------------------

    /**
     * @return string
     */
    private function build_img_tag()
    {
    	if(!isset($this->img_attrs['src']))
    	{
    		$this->return_data = '';
    	}

    	else
    	{
	    	$img  = '<img ';

	    	// src=""
	    	$img .= ' src="'.$this->img_attrs['src'].'" ';

            // alt=""
            $img .= ' alt="" ';

	    	// style=""
	    	$img .= ' style="';
	    	if($this->img_attrs['height']) $img .= 'height:'.intval($this->img_attrs['height']).'px;';
	    	if($this->img_attrs['width']) $img .= 'width:'.intval($this->img_attrs['width']).'px;';
	    	if($this->params['fill'] == 'yes')
	    	{
	    		if($this->img_attrs['width'] && $this->params['width'] && ($this->img_attrs['width'] > $this->params['width']))
	    			$img .= 'margin-left: -'.(int) floor(($this->img_attrs['width'] - $this->params['width']) / 2).'px;';
	    		if($this->img_attrs['height'] && $this->params['height'] && ($this->img_attrs['height'] > $this->params['height']))
	    			$img .= 'margin-top: -'.(int) floor(($this->img_attrs['height'] - $this->params['height']) / 2).'px;';
	    	}
	    	$img .= '" ';

	    	// class=""
	    	if($this->params['class'])
	    		$img .= ' class="'.$this->params['class'].'" ';

	    	// id=""
	    	if($this->params['id'])
	    		$img .= ' id="'.$this->params['id'].'" ';

	    	$img .= '>';

	    	$this->return_data = $img;
	    }

    	return $this->return_data;
    }

    // --------------------------------------------------------------------

    /**
     * @return boolean
     */
    private function ensure_cachedir()
    {
    	$cachedir = $this->upload_pref->server_path.$this->cachedir_name;

    	if(!file_exists($cachedir))
    	{
    		if(!mkdir($cachedir, 0755))
    			return $this->_err("could not create cache directory {$cachedir}.");
    	}

    	/* readable? */
    	if(!is_readable($cachedir))
    		return $this->_err("cache directory {$cachedir} is not readable.");

    	/* writable? */
    	if(!is_writable($cachedir))
    		return $this->_err("cache directory {$cachedir} is not writable.");

    	return TRUE;
    }

    // --------------------------------------------------------------------

    /**
     * @return boolean
     */
    private function _err($msg=null, $die=FALSE)
    {
    	if($msg)
    		error_log(__CLASS__.' Reported: '.$msg);

    	if($die === TRUE)
    		die();

    	return FALSE;
    }

    // --------------------------------------------------------------------

    /**
     * @return string
     */
    public function usage()
    {
        ob_start();

        echo $this->plugin_info['pi_author_url'];

        $buffer = ob_get_contents();

        ob_end_clean();

        return $buffer;
    }
}
/* End of file pi.photomule.php */
/* Location: ./system/expressionengine/third_party/photomule/pi.photomule.php */
