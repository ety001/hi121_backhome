<?php
/*******************************************************************
 * @Filename admincp.inc.php $
 *
 * @Author ��ҵ�� ETY001 http://www.domyself.me $
 * 
 * @Declare �ò��רΪ121����(http://www.hi121.net)������� $
 *
 * @Date 2012-01-01 21:58:44 $
 *******************************************************************/

if(!defined('IN_JISHIGOU'))
{
    exit('invalid request');
}
//����ò���ĸ�Ŀ¼
define('PLUGIN_HI121_BACKHOME_PATH',ROOT_PATH.'plugin/hi121_backhome');
//��ȡ��վ��������Ϣ
$site_config = $this->Config;
//��ȡ�����ʼ��Ĳ�����Ϣ
$smtp_config = ConfigHandler::get('smtp');
//���ύ��ַ��ȡ���ID
$pluginid = is_numeric($_GET['id']) ? $_GET['id'] : 0;
//$page = is_numeric($_GET['page']) ? $_GET['page'] : 0;
//��ʱʹ�����ݿⷽʽ��ȡ���������Ϣ���ٷ��ĵ�˵�����û�����ƶ�ȡ����ʱ��֪���ʹ�û����ȡ�������Ϣ
$sqls = "SELECT * FROM `" . TABLE_PREFIX . "pluginvar` WHERE `pluginid`='$pluginid'";
$querys = $this->DatabaseHandler->Query($sqls);
while($rows = $querys->GetRow())
{
	//$pluginvar[] = $rows;
	//��ȡ���ñ�����Ϣ
	//$pluginvar['tplURL'] ģ���ַ
	//$pluginvar['everyNum'] ÿ����������
	//$pluginvar['interval'] �೤ʱ��û�е�¼ 
	//$pluginvar['mail_title'] �ʼ�����
	//$pluginvar['send_interval'] ÿ���ķ��ͼ��
	//$pluginvar['send_method'] ���ͷ�ʽ
	//$pluginvar['test_mod'] ����ģʽ
	//$pluginvar['log_name'] ��־����
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
		//��ֹ�ظ�ִ��
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
		
	//�����û��ر�ҳ��
	ignore_user_abort();
	//���ó�ʱʱ������
	set_time_limit(0);
	$start_time = time();
	$results = '';//������
	
	$mail_subject = $pluginvar['mail_title'];//�ʼ�����
	$tempContent = '';//�ʼ����ݸ�ʽ
	$tempFile = fopen(PLUGIN_HI121_BACKHOME_PATH.$pluginvar['tplURL'],'r');
	if(flock($tempFile,LOCK_SH))//�ļ���
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
	@fclose($tempFile);//����ϴ�ִ�����ɵ���־
	
	foreach($user as $k => $v)
	{
		//$tempContentֻ�Ƿ����ʼ���ģ�壬�������ģ��������
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
		$results .= $send_result?$v['email'].'�ʼ����ͳɹ���':$v['email'].'�ʼ�����ʧ�ܣ�';
		$results .= "\r\n\n";
		$i++;
		if($i == $pluginvar['everyNum'])
		{
			$results .= date("Y-m-d H:i:s",time())."��{$i}���û������ʼ�\n";
			$tempFile = @fopen(PLUGIN_HI121_BACKHOME_PATH.'/log/'.$pluginvar['log_name'].'.txt','a+');
			@fwrite($tempFile,$results);
			@fclose($tempFile);
			$results = '';
			$i = 0;
			sleep(SLEEP_TIME);
		}
	}
	
	$end_time = time();
	$results .= '���з��������û����ѷ��͹��ʼ�����ʱ '.($end_time-$start_time)." ��\n\n\n";
	$tempFile = @fopen(PLUGIN_HI121_BACKHOME_PATH.'/log/'.$pluginvar['log_name'].'.txt','a+');
	@fwrite($tempFile,$results);
	@fclose($tempFile);
	$lockfile = @fopen(PLUGIN_HI121_BACKHOME_PATH.'/log/lockfile.lock','w');
	@fwrite($lockfile,'0');
	@fclose($lockfile);
}
?>
