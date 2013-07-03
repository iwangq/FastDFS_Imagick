<?PHP
/**
 * FastDFS + imagick图片上传服务
 * @Author Uxin <iwangq@gmail.com><jorygong@gmail.com>
 * @Modify 2013-05-29 21:12:21
 *
 * 通过POST方式上传图片或图片URL，本程序返回存储后的图片路径和尺寸。
 * @param app 应用名，key 应用密钥
 * @param method，默认post文件流$_FILES['pic'],form表单
 *  method=url以POST图片URL地址的方式$_POST['pic']='http://img.....jpg'，自动去抓取并存储。
 *  method=buff以POST图片文件buff方式上传$_POST['pic']=图片文件二进制数据
 * @return width图片宽度，height图片高度。
 */

require('config.php');
require('imagick.class.php');

//验证app key
if ( !$_POST['app'] || !$_CONFIG_APP_KEY[$_POST['app']] || ($_CONFIG_APP_KEY[$_POST['app']] != $_POST['key']) )
{
    err_ret('app key error.');
}

if ($_POST['method'] == 'url') //URL方式上传
{
//    require('curl.class.php');
//    $curl = new curl_class();
//    $curl->get($_POST['pic']);
//    if ($buff = $curl->currentResponse('body'))
    if ($buff = file_get_contents($_POST['pic']))
    {
	$imagick = new Imagick();
	if ( $imagick->readImageBlob($buff) )
	{
	    resize_upload($imagick);
	}
	else
	{
	    err_ret("url's content is not pic.");
	}

    }
    else
    {
	err_ret('file not exists.');
    }
    
}
elseif ($_POST['method'] == 'buff') //POST buff方式上传
{
    if ($buff = $_POST['pic'])
    {
	$imagick = new Imagick();
	if ( $imagick->readImageBlob($buff) )
	{
	    resize_upload($imagick);
	}
	else
	{
	    err_ret("post buff is not pic.");
	}
	
    }
    else
    {
	err_ret('file buff is empty.');
    }
}
else
{
    if ($_FILES['pic']['size'])
    {
	$imagick = new Imagick();
	if ( $imagick->readImage($_FILES['pic']['tmp_name']) )
	{
	    unlink($_FILES['pic']['tmp_name']);
	    resize_upload($imagick);

	}
	else
	{
	    err_ret("post file is not pic.");
	}
	
    }
    else
    {
	err_ret('post file is empty.');
    }    
}
//处理图片
function resize_upload($imagick)
{
    global $_CONFIG_MAX_SIZE;
    $width = $imagick->getImageWidth();
    $height = $imagick->getImageHeight();
    if ($width > $height && $width > $_CONFIG_MAX_SIZE)
    {
	$width = $_CONFIG_MAX_SIZE;
	$imagick->resizeImage($_CONFIG_MAX_SIZE, $_CONFIG_MAX_SIZE, Imagick::FILTER_CATROM, 1, true);
	$height = $imagick->getImageHeight();
    }
    elseif ($height > $width && $height > $_CONFIG_MAX_SIZE)
    {
	$height = $_CONFIG_MAX_SIZE;
	$imagick->resizeImage($_CONFIG_MAX_SIZE, $_CONFIG_MAX_SIZE, Imagick::FILTER_CATROM, 1, true);
	$width = $imagick->getImageWidth();
    }
    $file_ext_name = strtolower($imagick->getImageFormat());
    if (!$file_ext_name || $file_ext_name == 'jpeg')
    {
	$imagick->setImageFormat('jpeg');
	$file_ext_name = 'jpg';
    }
    $buff = $imagick->getImageBlob();
    if ($ret = fastdfs_storage_upload_by_filebuff1($buff, $file_ext_name))
    {
	$ret = '/'.$ret;
	ok_ret($ret, $width, $height);
	exit;
    }
    else
    {
	err_ret('upload fail.');
    }
    
}

/**
 * 成功时返回
 * @param $pic 文件名
 * @param $width 宽
 * @param $height 高
 */
function ok_ret($pic, $width, $height)
{
    if ($_POST['backurl'])
    {
	$url = $_POST['backurl'].'?code=1&pic='.$pic.'&width='.$width.'&height='.$height;
	header('location:'.$url);
    }
    else
    {
	$json = array('code'=>1, 'pic'=>$pic, 'width'=>$width, 'height'=>$height);
	echo json_encode($json);
    }
    exit;
}
//失败返回信息
function err_ret( $msg )
{
    if ($_POST['backurl'])
    {
	$url = $_POST['backurl'].'?code=-1&msg='.$msg;
	header('location:'.$url);
    }
    else
    {
	$json = array('code'=>-1, 'msg'=>$msg);
	echo json_encode($json);
    }
    exit;
}