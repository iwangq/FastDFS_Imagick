<?PHP
/**
 * FastDFS + imagick图片服务，自动生成缩略图上传到FastDFS并返回给浏览器
 * @Author Uxin <iwangq@gmail.com><jorygong@gmail.com>
 * @Modify 2013-05-20 21:12:21
 */

require('config.php');
require('imagick.class.php');

$url = $_SERVER['REQUEST_URI'];
if (!preg_match('/^\/([0-9a-z_\-\/]{43})_([0-9a-z]{2})\.(jpg|png|gif|bmp)$/i', $url, $match))
{
    err404();
}
//print_r($match);exit;
//原图
$remote_filename = $match[1].'.'.$match[3];
//echo $remote_filename;

//尺寸编码
$config = $_CONFIG_IMG_SIZE[$match[2]];
if (!$config) //请求尺寸不存在
{
    err404();
}

if (!$config['quality'])
{
    $config['quality'] = $_CONFIG_COMPRESS_QUALITY;
}
if ($config['wmp']) //需要添加水印
{
    if (!$config['wmi'])
    {
	$config['wmi'] = $_CONFIG_WATERMARK_IMG;
    }
    if (!$config['wmm'])
    {
	$config['wmm'] = $_CONFIG_WATERMARK_MARGIN;
    }
}

//下载原图
$imgdata = fastdfs_storage_download_file_to_buff1($remote_filename);
if (!$imgdata)
{
    err404();
}
//压缩处理
$img = new imagick_class();
$ret = $img->compress_buff($imgdata, $config['width'], $config['height'], $config);
$imgdata = $ret['buff'];

//显示
header('Content-Type: image/'.$ret['format']);
header('Content-Length: '.strlen($imgdata));
echo $imgdata;

//上传
$prefix_name = '_'.$match[2];
$ext_name = $match[3];
fastdfs_storage_upload_slave_by_filebuff1($imgdata, $remote_filename, $prefix_name, $ext_name);



/**
 * 返回404
 */
function err404()
{
    header('HTTP/1.1 404 Not Found');
    header("status: 404 Not Found");
    exit;
}
