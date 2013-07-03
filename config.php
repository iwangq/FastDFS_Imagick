<?PHP
/**
 * FastDFS + imagick 压缩图片配置
 * @Author Uxin <iwangq@gmail.com><jorygong@gmail.com>
 * @modify 2013-05-20 21:05:09
 *
 */

/**
 * 项目定义
 * array(app => key,...)
 * array(应用名称=>密钥,...)
 * 
 */
$_CONFIG_APP_KEY = array(
    'test' => 'youxinpai',
);
 
//宽高最大值，为保证原始文件过大
$_CONFIG_MAX_SIZE = 1600;
//默认压缩质量
$_CONFIG_COMPRESS_QUALITY = 85;

//默认水印图片
$_CONFIG_WATERMARK_IMG = 'watermark/youxinpai.png';

//默认水印边距
$_CONFIG_WATERMARK_MARGIN = 20;
/**
 * 图片路径对应尺寸
 * 两位字符图片后缀，支持_[0-9a-z]{2}路径
 * 后缀名 => array(...)
 * 'width'=>宽：生成图片的宽度
 * 'height'=>高：生成图片的高度
 * 'model'=>压缩模式：默认模式表示剪切压缩，为1表示尺寸不超过定义的宽和高，为2表示居中四周补白边，
 * 'quality'=>压缩质量，为0使用默认压缩质量
 * 'wmp'=>水印位置：（象限表示法）默认0不添加水印，1右上角，2右下角，3左下角，4左上角，5中心，6随机。
 * 'wmi'=>水印图片：水印图片文件路径，相对于当前文件路径或绝对路径。
 * 'wmm'=>水印边距，为0使用默认。
 */
$_CONFIG_IMG_SIZE = array(
    '01' => array(
	'width' => 800,
	'height' => 600,
	'wmp' => 1,
	),
    '02' => array(
	'width' => 640,
	'height' => 480,
	'model' => 1,
	'wmp' => 2,
	),
    '03' => array(
	'width' => 640,
	'height' => 480,
	'model' => 2,
	'wmp' => 3,
	),
    '04' => array(
	'width' => 640,
	'height' => 480,
	'wmp' => 4,
	),
    '05' => array(
	'width' => 640,
	'height' => 480,
	'model' => 1,
	'wmp' => 5,
	),
    '06' => array(
	'width' => 640,
	'height' => 480,
	'model' => 2,
	'wmp' => 6,
	),
);