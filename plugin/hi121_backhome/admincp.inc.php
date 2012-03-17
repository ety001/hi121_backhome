<?php
/*******************************************************************
 * @Filename admincp.inc.php $
 *
 * @Author 于业超 ETY001 http://www.domyself.me $
 * 
 * @Declare 该插件专为121部落(http://www.hi121.net)开发设计 $
 *
 * @Date 2012-01-01 21:58:44 $
 *******************************************************************/

if(!defined('IN_JISHIGOU'))
{
    exit('invalid request');
}
//定义该插件的根目录
define('PLUGIN_HI121_BACKHOME_PATH',ROOT_PATH.'plugin/hi121_backhome');
//获取网站的配置信息
$site_config = $this->Config;
//获取发送邮件的参数信息
$smtp_config = ConfigHandler::get('smtp');
//从提交地址获取插件ID
$pluginid = is_numeric($_GET['id']) ? $_GET['id'] : 0;
//$page = is_numeric($_GET['page']) ? $_GET['page'] : 0;
//暂时使用数据库方式读取插件配置信息，官方文档说可以用缓存机制读取，暂时不知如何使用缓存读取到插件信息
$sqls = "SELECT * FROM `" . TABLE_PREFIX . "pluginvar` WHERE `pluginid`='$pluginid'";
$querys = $this->DatabaseHandler->Query($sqls);
while($rows = $querys->GetRow())
{
	//$pluginvar[] = $rows;
	//读取配置变量信息
	//$pluginvar['tplURL'] 模板地址
	//$pluginvar['everyNum'] 每批发送人数
	//$pluginvar['interval'] 多长时间没有登录 
	//$pluginvar['mail_title'] 邮件标题
	//$pluginvar['send_interval'] 每批的发送间隔
	//$pluginvar['send_method'] 发送方式
	//$pluginvar['test_mod'] 测试模式
	//$pluginvar['log_name'] 日志名称
	$pluginvar[$rows['variable']] = $rows['value'];
}

define('SLEEP_TIME',$pluginvar['send_interval']*60);


$query_num = "SELECT count(*) as num FROM `" . TABLE_PREFIX . "members` WHERE (".time()."-lastvisit)>(3600*24*{$pluginvar['interval']})";
$num = $this->DatabaseHandler->Query($query_num);
while($n = $num->GetRow())
{
	$total = $n['num'];
}
$total_page = (int)($total/$pluginvar['everyNum'])+1;
$total_time = ($total_page-1)*$pluginvar['send_interval'];

if($_GET['pluginop'] == 'start')
{
	if(!$pluginvar['test_mod'])
	{
		//防止重复执行
		$lockfile = fopen(PLUGIN_HI121_BACKHOME_PATH.'/log/lockfile.lock','r');
		$lockTxt = fgetc($lockfile);
		if($lockTxt == 1)
		{
			$output = '121backhome is running';
			fclose($lockfile);
			exit();
		}
		else
		{
			fclose($lockfile);
			$lockfile = fopen(PLUGIN_HI121_BACKHOME_PATH.'/log/lockfile.lock','w');
			fwrite($lockfile,'1');
		}
		fclose($lockfile);
	}
		
	//忽略用户关闭页面
	ignore_user_abort();
	//设置超时时间无限
	set_time_limit(0);
	$start_time = time();
	$results = '';//输出结果
	
	$mail_subject = $pluginvar['mail_title'];//邮件标题
	$tempContent = '';//邮件内容格式
	$tempFile = fopen(PLUGIN_HI121_BACKHOME_PATH.$pluginvar['tplURL'],'r');
	if(flock($tempFile,LOCK_SH))//文件锁
	{
		while(!feof($tempFile))
		{
			$tempContent .= fgets($tempFile);
		}
		flock($tempFile,LOCK_UN);
	}
	fclose($tempFile);
	
	Load::lib('mail');
	
	/* 
	$curtten = $page*$pluginvar['everyNum'];
	$sql = "SELECT * FROM `" . TABLE_PREFIX . "members` WHERE (".time()."-lastvisit)>(3600*24*{$pluginvar['interval']}) LIMIT {$curtten} , {$pluginvar['everyNum']}";
	 * */
	$sql = "SELECT * FROM `" . TABLE_PREFIX . "members` WHERE (".time()."-lastvisit)>(3600*24*{$pluginvar['interval']})";
	$query = $this->DatabaseHandler->Query($sql);
	$i = 0;
	while($user[$i] = $query->GetRow())
	{
		$i++;
	}
	unset($query);
	unset($user[$i]);
	$i=0;
	
	$tempFile = @fopen(PLUGIN_HI121_BACKHOME_PATH.'/log/'.$pluginvar['log_name'].'.txt','w');
	@fclose($tempFile);//清空上次执行生成的日志
	
	foreach($user as $k => $v)
	{
		//$tempContent只是发送邮件的模板，如果想在模板中引用
		$mail_content = sprintf($tempContent,$v['nickname'],$pluginvar['interval']);
		if($v['email'])
		{
			if(!$pluginvar['test_mod'])
			{
				$send_result = @send_mail(
					$v['email'],
					$mail_subject,
					$mail_content,
					$sys_config['site_admin_nickname'],
					$smtp_config['mail']
				);
			}
		}
		unset($user[$k]);
		$results .= $send_result?$v['email'].'邮件发送成功！':$v['email'].'邮件发送失败！';
		$results .= "\r\n\n";
		$i++;
		if($i == $pluginvar['everyNum'])
		{
			$results .= date("Y-m-d H:i:s",time())."向{$i}个用户发送邮件\n";
			$tempFile = @fopen(PLUGIN_HI121_BACKHOME_PATH.'/log/'.$pluginvar['log_name'].'.txt','a+');
			@fwrite($tempFile,$results);
			@fclose($tempFile);
			$results = '';
			$i = 0;
			sleep(SLEEP_TIME);
		}
	}
	
	$end_time = time();
	$results .= '所有符合条件用户都已发送过邮件！用时 '.($end_time-$start_time)." 秒\n\n\n";
	$tempFile = @fopen(PLUGIN_HI121_BACKHOME_PATH.'/log/'.$pluginvar['log_name'].'.txt','a+');
	@fwrite($tempFile,$results);
	@fclose($tempFile);
	$lockfile = @fopen(PLUGIN_HI121_BACKHOME_PATH.'/log/lockfile.lock','w');
	@fwrite($lockfile,'0');
	@fclose($lockfile);
}
?>
