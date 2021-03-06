<?php
/**
 * Gas station V1.3
 */

/**
 * 传入正规拼写的番号, 返回查询到的磁链, 查不到则返回false
 *
 * @param string $code
 * @param bool   $hd
 * @return mixed
 */
function get_magnet($code, $hd = true)
{
    // 要求输入必须严格, 例ABS-130, 反之则可能导致结果不精确
    $query_url = 'http://www.javbus.com/' . $code;
    $bt_url = 'https://www.torrentkitty.tv/search/' . $code;  // 搜索磁力链接url(备用)(不精确)

    $cover_pattern = '/<a class="bigImage" href="(.+?)">/';
    $gid_pattern = '/gid *= *(\d+)/';
    $uc_pattern = '/uc *= *(\d+);/';
    // 以上三个正则用于匹配ajax查询jab_bus上的磁链所需要的参数

    $magnet_pattern = '#<a href="(magnet:\?xt=urn:btih:\w{40}).*?".+?rel="magnet">Open</a>#';  // 用于匹配 $bt_url 中搜索结果的正则
    $hd_mag_pattern = '/href="(magnet:\?xt=urn:btih:\w{40}).*?">\s*.+\s*<a class="btn btn-mini-new btn-primary disabled"/';  // 用于匹配javbus高清搜索结果的正则
    $normal_mag_pattern = '/window\.open\(\'(magnet:\?xt=urn:btih:\w{40}).*?_self\'\)/';  // 用于匹配javbus标清搜索结果的正则

    $res = file_get_contents($query_url);

    preg_match($gid_pattern, $res, $gid_match);
    preg_match($uc_pattern, $res, $uc_match);
    preg_match($cover_pattern, $res, $cover_match);

    $get_magnet_url = 'https://www.javbus.com/ajax/uncledatoolsbyajax.php?gid=' . $gid_match[1] . '&lang=zh&img=' . $cover_match[1] . '&uc=' . $uc_match[1] . '&floor=' . rand(1, 1000);

    // 伪造ajax查询所必要的headers
    $res = file_get_contents($get_magnet_url, false, stream_context_create(array('http' => array('header' => "Referer: https://www.javbus.com\r\nCookie: existmag=mag\r\n"))));

    preg_match_all($hd_mag_pattern, $res, $hd_mag_match);
    preg_match_all($normal_mag_pattern, $res, $normal_mag_match);

    if ($hd) {
        // 需要高清
        if ($hd_mag_match[1]) {
            $response = $hd_mag_match[1][0];

        } else {
            if ($normal_mag_match[1]) {
                $response = $normal_mag_match[1][0];
            } else {
                // javbus页面找不到磁链, 只好去 $bt_url 找, 结果可能不精确

                $res = file_get_contents($bt_url, false, stream_context_create(array('http' => array('method' => "GET", 'header' => "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.99 Safari/537.36\r\n"))));

                preg_match($magnet_pattern, $res, $magnet_match);
                if ($magnet_match[1]) {
                    $response = $magnet_match[1];
                } else {
                    // 这下是真的找不到了
                    $response = false;
                }
            }
        }
    } else {
        // 不需要高清
        if ($normal_mag_match[1]) {
            $response = $normal_mag_match[1][0];
        } else {
            // javbus页面找不到磁链, 只好去 $bt_url 找, 结果可能不精确

            $res = file_get_contents($bt_url, false, stream_context_create(array('http' => array('method' => "GET", 'header' => "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.99 Safari/537.36\r\n"))));

            preg_match($magnet_pattern, $res, $magnet_match);
            if ($magnet_match[1]) {
                $response = $magnet_match[1];
            } else {
                // 这下是真的找不到了
                $response = false;
            }
        }
    }

    return $response;
}

/**
 * 传入搜索结果唯一的番号, 需要正规拼写, 返回查询到的信息(带磁链)
 *
 * @param string $code
 * @return string
 */
function get_info($code)
{
    $query_url = 'http://www.javbus.com/' . $code;

    $title_pattern = '/<h3>(.+)<\/h3>/';
    $date_pattern = '/<\/span> *(\d+-\d+-\d+) *<\/p>/';
    $star_pattern = '/<div class="star-name">.+title="(.+)"/';
    $cover_pattern = '/<a class="bigImage" href="(.+?)">/';
    $pic_pattern = '/<a class="sample-box" href="(.+?)">/';

    $res = file_get_contents($query_url);

    preg_match($title_pattern, $res, $title_match);
    preg_match($date_pattern, $res, $date_match);
    preg_match_all($star_pattern, $res, $star_match);
    preg_match($cover_pattern, $res, $cover_match);
    preg_match_all($pic_pattern, $res, $pic_match);

    if ($star_match[1]) {
        $star = implode('; ', $star_match[1]);
    } else {
        $star = '暂无司机数据';
    }
    if ('0000-00-00' == $date_match[1]) {
        $date_match[1] = '未知';
    }
    $response = '车牌&车型: ' . $title_match[1]
        . "\n" . '发车日期: ' . $date_match[1]
        . "\n" . '司机: ' . $star;


    $response .= "\n" . '<a target="_blank" href="' . get_img($cover_match[1]) . '">封面图</a>';


    if ($pic_match[1]) {
        $response .= "\n" . '<a target="_blank" href="' . make_preview($pic_match[1], $code) . '">截图</a>';
    }

    $magnet = get_magnet($code) ?: '找不到神秘代码';
    $response .= "\n" . $magnet;

    return $response;
}

/**
 * 传入图片url数组和番号(用作alt属性值)
 * 返回包含图片的html页面的url
 * 默认存放在preview目录下
 *
 * @param array  $picUrl
 * @param string $code
 * @param string $dirName
 * @return string
 */
function make_preview($picUrl, $code, $dirName = 'preview')
{
    $path = $_SERVER['DOCUMENT_ROOT'] . "/$dirName/";
    $filename = $code . '.html';
    if (!file_exists($path . $filename) || (filesize($path . $filename) === 0)) {
        $result = '';
        foreach ($picUrl as $value) {
            $result .= '<img src="' . $value . '" alt="' . $code . '">';
        }
        touch($path . $filename);
        file_put_contents($path . $filename, $result);
    }

    return 'http://' . $_SERVER['HTTP_HOST'] . "/$dirName/$filename";
}

/**
 * 通过传入url下载该图片, 并返回下载到服务器后的url
 *
 * @param string $picUrl
 * @param string $dirName
 * @return string
 */
function get_img($picUrl, $dirName = 'tmp')
{
    $path = $_SERVER['DOCUMENT_ROOT'] . "/$dirName/";
    $filename = end(explode('/', $picUrl));
    if (!file_exists($path . $filename) || (filesize($path . $filename) === 0)) {
        touch($path . $filename);
        file_put_contents($path . $filename, file_get_contents($picUrl));
    }

    return 'http://' . $_SERVER['HTTP_HOST'] . "/$dirName/$filename";
}

/**
 * 传入用户的原始输入, 返回查询到的信息(带磁链), 查不到则返回jav_lib高评价里的随机番号
 *
 * @param string $code
 * @return string
 */
function origin_query($code)
{
    $search_url = 'https://www.javbus.com/search/' . $code;  // 按番号搜索

    $movie_pattern = '/<a class="movie-box" href="(.+)">/';  // 单页影片数
    $pages_pattern = '/<a href="\/search\/' . $code . '\/(\d+)">\d+/';  // 页数

    $res = file_get_contents($search_url);

    preg_match_all($movie_pattern, $res, $movie_match);

    if (count($movie_match[1]) == 1) {
        // 用户搜索结果唯一
        //echo $movie_match[1][0];
        $code = explode('/', $movie_match[1][0]);

        //echo end($code);  // 获取正规拼写的番号
        return get_info(end($code));
    } else {
        // 用户搜索结果不唯一, 可能需要翻页处理
        preg_match_all($pages_pattern, $res, $pages_match);
        //print_r($pages_match[1]);
        $res = file_get_contents('https://www.javbus.com/uncensored/search/' . $code . '&type=1');
        preg_match_all($pages_pattern, $res, $unpages_match);  // 无码页数
        preg_match_all($movie_pattern, $res, $unmovie_match);
        if (count($pages_match[1]) || count($unpages_match[1])) {
            // 需要翻页
            $response = '结果太多, 尝试缩小搜索范围吧';

            return $response;
        } else {
            // 只有一页或者没找到
            //print_r(count($movie_match[1]));
            if (count($movie_match[1]) == 0) {
                $randcode = randCode();

                return '没有此车牌, 试试' . $randcode;
            } else {
                // 此时两种情况, 一种是搜SW-220, 结果有DKSW-220 和 SW-220, 另一种则是寻常的模糊搜索
                if (in_array('https://www.javbus.com/' . $code, $movie_match[1]) || in_array('https://www.javbus.com/' . $code, $unmovie_match[1])) {
                    // 此时便是搜SW-220, 结果有DKSW-220 和 SW-220 的情况, 需要返回SW-220的信息
                    $response = get_info($code);
                } else {
                    // 此时是正常的模糊查询
                    $response = "你是不是要找:";
                    sort($movie_match[1]);  // 按照号码大小排序, 默认顺序是按照影片热门度排序的
                    foreach ($movie_match[1] as $v) {
                        $arr = explode('/', $v);
                        $response = $response . "\n" . $arr[3];
                    }
                    sort($unmovie_match[1]);  // 按照号码大小排序, 默认顺序是按照影片热门度排序的
                    foreach ($unmovie_match[1] as $v) {
                        $arr = explode('/', $v);
                        $response = $response . "\n" . $arr[3];
                    }
                }

                return $response;
            }
        }
    }
}

/**
 * 通过jav_library的高评价页面随机获取番号, 只获取前十页
 * 用于用户输入非法时返回给用户
 *
 * @return string
 */
function randCode()
{
    $best_rated = 'http://www.javlibrary.com/tw/vl_bestrated.php?list&mode=&page=' . rand(1, 10);

    $code_pattern = '/<a href="\.\/\?v=.+?" title="(\S+) {1}/';

    $res = file_get_contents($best_rated);

    preg_match_all($code_pattern, $res, $code_match);

    //print_r($code_match);
    $response = $code_match[1][rand(0, count($code_match[1]))];
    $response = empty($response) ? randCode() : $response;  // 防止返回空
    return $response;
}
