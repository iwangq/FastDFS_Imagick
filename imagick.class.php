<?PHP
/**
 * imagick 压缩图片类
 * @Author Uxin <iwangq@gmail.com><jorygong@gmail.com>
 * @modify 2013-05-20 21:05:09
 *
 */

class imagick_class
{
    /**
     * 压缩图片,二进制方式
     * @param $buff 源图片二进制内容（必选）
     * @param $width 生成图片宽（必选）
     * @param $height 生成图片高（必选）
     * @param $config['model'] 压缩方式，默认剪切压缩，对应配置文件config.php压缩方式
     * @param $config['quality'] 压缩质量
     * @param $config['wmp'] 水印位置，为0不添加水印,对应配置文件config.php水印位置
     * @param $config['wmi'] 水印图片路径，只有当添加水印时有效，为空使用默认水印图片
     * @param $config['wmm'] 水印图片边距，默认20
     * @return array('buff'=>生成的图片二进制内容,'width'=>宽,'height'=>高)
     */
    public function compress_buff($buff, $width, $height, $config=array())
    {
	$imagick = new Imagick();
	$imagick->readImageBlob($buff);
	$format = $imagick->getImageFormat();
	//不同压缩模式分别处理
	if ($config['model'] == 1) //自适应
	{
	    $imagick->resizeImage($width, $height, Imagick::FILTER_CATROM, 1, true);
	    $width = $imagick->getImageWidth();
	    $height = $imagick->getImageHeight();
	    if (!$width || !$height)
	    {
		return false;
	    }
	    
	}
	elseif ($config['model'] == 2) //四周补白
	{
	    $w = $imagick->getImageWidth();
	    $h = $imagick->getImageHeight();
	    $background = new ImagickPixel('white');
	    if (!$w || !$h)
	    {
		return false;
	    }
	    if ($w/$h > $width/$height) //上下补白
	    {
		//以宽度进行缩放
	        $imagick->resizeImage($width, 3600, Imagick::FILTER_CATROM, 1, true);
		$w = $width;
	        $h = $imagick->getImageHeight();
		//补白
		$canvas = new Imagick();
		$canvas->newImage($width, $height, $background, $format);
		$canvas->compositeImage($imagick, Imagick::COMPOSITE_OVER, 0, floor(($height-$h)/2));
		$imagick = $canvas;
	    }
	    else
	    {
		//以高度进行缩放
	        $imagick->resizeImage(3600, $height, Imagick::FILTER_CATROM, 1, true);
	        $w = $imagick->getImageWidth();
		$h = $height;
		//补白
		$canvas = new Imagick();
		$canvas->newImage($width, $height, $background, $format);
		$canvas->compositeImage($imagick, Imagick::COMPOSITE_OVER, floor(($width-$w)/2), 0);
		$imagick = $canvas;
	    }
	}
	else //缩放后裁边
	{
	    $w = $imagick->getImageWidth();
	    $h = $imagick->getImageHeight();
	    if (!$w || !$h)
	    {
		return false;
	    }
	    if ($w/$h > $width/$height)
	    {
		//以高度进行缩放
	        $imagick->resizeImage(3600, $height, Imagick::FILTER_CATROM, 1, true);
		$imagick->cropImage($width, $height, ceil(($height*$w/$h-$width)/2) , 0);
	    }
	    else
	    {
		//以宽度进行缩放
	        $imagick->resizeImage($width, 3600, Imagick::FILTER_CATROM, 1, true);
		$imagick->cropImage($width, $height, 0, ceil(($width*$h/$w-$height)/2));
	    }
	}

	//添加水印 todo...
	if ($config['wmp'])
	{
	    $water = new Imagick($config['wmi']);
	    $water_page = $water->getImagePage();
	    $w = $water_page['width'];
	    $h = $water_page['height'];
	    if ($config['wmp'] == 1)
	    {
		$x = $width - $w - $config['wmm'];
		$y = $config['wmm'];
	    }
	    elseif ($config['wmp'] == 2)
	    {
		$x = $width - $w - $config['wmm'];
		$y = $height - $h - $config['wmm'];
		
	    }
	    elseif ($config['wmp'] == 3)
	    {
		$x = $config['wmm'];
		$y = $height - $h - $config['wmm'];
	    }
	    elseif ($config['wmp'] == 4)
	    {
		$x = $y = $config['wmm'];
	    }
	    elseif ($config['wmp'] == 5)
	    {
		$x = ceil(($width - $w)/2);
		$y = ceil(($height - $h)/2);
	    }
	    else
	    {
		$x = rand(($config['wmm']), ($width - $w - $config['wmm']));
		$y = rand(($config['wmm']), ($height - $h - $config['wmm']));
	    }
	    $imagick->compositeImage($water, Imagick::COMPOSITE_OVER, $x, $y);
	}
	//设置压缩质量
	if ($config['quality'])
	{
	    $imagick->setImageCompressionQuality($config['quality']);
	}
	
	//去除exif信息
	$imagick->stripImage();
	$buff = $imagick->getImageBlob();
	return array('buff'=>$buff, 'width'=>$width, 'height'=>$height, 'format'=>$format);
    }

    
}
