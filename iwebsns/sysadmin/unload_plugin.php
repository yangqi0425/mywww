<?php
require("session_check.php");
require("../api/Check_MC.php");
$is_check=check_rights("d03");
if(!$is_check){
	echo 'no permission';
	exit;
}
$pl_langpackage=new pluginslp;
$plugin_table=$tablePreStr."plugins";
$t_plugin_url=$tablePreStr."plugin_url";
$t_users = $tablePreStr."users"; 
$t_backgroup=$tablePreStr."backgroup";
$t_frontgroup=$tablePreStr."frontgroup";
$plugin_base=dirname(__file__)."/../plugins";
require_once(dirname(__file__)."/../foundation/cxmloperator.class.php");
require_once(dirname(__file__)."/../foundation/ftpl_compile.php");
$path=get_args('path');
?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title></title>
</head>
<body style="text-align:center">
	<div style="width:100%" >
<?php
if(!is_null($path))
{

	//检测是不是有数据库文件
	$create_tables="$plugin_base/$path/plugin_create_tables.php";
	$dbo = new dbex;
	dbtarget('w',$dbServs);            
	if(file_exists($create_tables))
	{
		$tables=explode(';',file_get_contents($create_tables));
		foreach($tables as $table)
		{
			$sql="drop table ".$table;
			if($dbo->exeUpdate($sql)) echo "<div style='color:green'>".$pl_langpackage->pl_table.":".$table.$pl_langpackage->pl_delete_successful."<br /></div>";
		}
		unlink($create_tables);
	}
	if(rename("$plugin_base/$path/_plugin.xml","$plugin_base/$path/plugin.xml"))
	{   
		$dom = new DOMDocument();
		$dom->load("$plugin_base/$path/plugin.xml");
		$plugin=$dom->getElementsByTagName("plugin")->item(0);
		$plugin_id=$plugin->getElementsByTagName("plugin_id")->item(0);
		$autoorder=$plugin->getElementsByTagName('autoorder')->item(0);
		$valid=$plugin->getElementsByTagName('valid')->item(0);
		if($plugin_id)$plugin->removeChild($plugin_id);
		if($autoorder)$plugin->removeChild($autoorder);
		if($valid)$plugin->removeChild($valid);
		$dom->formatOutput=true;
		$dom->save("$plugin_base/$path/plugin.xml");
		//清除权限资源
		$xmlpath=$webRoot."plugins/resources.xml";
		$xml=new XMLOperator($xmlpath);
		if($xml->query("//group[@id='plugin_$path']"))
		{
			$sql="select id,rights from $t_backgroup where rights like '%{$path}_%'";
			dbtarget('r',$dbServs);
			$groups=$dbo->getRs($sql);
			dbtarget('w',$dbServs);
			
			if(!empty($groups))
			{
				foreach($groups as $group)
				{
					$rights=preg_replace("/{$path}[^,]+(,?)/","",$group['rights']);
					if(substr($rights,-1)==',')$rights=substr($rights,0,-1);
					$sql="update $t_backgroup set rights='$rights' where id=$group[id]";
					$dbo->exeUpdate($sql);
				}
			}
			
			$xml->delNode("//group[@id='plugin_$path']");
			$xml->save($xmlpath);
		}
		
		$xmlpath=$webRoot."plugins/front_resources.xml";
		$xml=new XMLOperator($xmlpath);
		if($xml->query("//group[@id='plugin_$path']"))
		{
			$sql="select id,rights from $t_frontgroup where rights like '%{$path}_%'";
			dbtarget('r',$dbServs);
			$groups=$dbo->getRs($sql);
			dbtarget('w',$dbServs);
			
			if(!empty($groups))
			{
				foreach($groups as $group)
				{
					$rights=preg_replace("/{$path}[^,]+(,?)/","",$group['rights']);
					if(substr($rights,-1)==',')$rights=substr($rights,0,-1);
					$sql="update $t_frontgroup set rights='$rights' where id=$group[id]";
					$dbo->exeUpdate($sql);
				}
			}
			
			$xml->delNode("//group[@id='plugin_$path']");
			$xml->save($xmlpath);
		}
        /**
        * @desc add by leon
        * @use to delete the record from the isns_user for the use_apps
        */
        $pluginIdSql = "select id from $plugin_table where name='$path'";
        if($plugin_rs = $dbo->getRs($pluginIdSql))
        {
            $userSql = "select user_id,use_apps from $t_users where use_apps like '%,".$plugin_rs[0]['id'].",%'"; 
            $users = $dbo->getRs($userSql);
            foreach($users as $user)
            {
                $upSql = "update $t_users set use_apps='".str_replace(",".$plugin_rs[0]['id']."," , "," , $user['use_apps'])."' where user_id=$user[user_id]";
                $userUnsetId = $dbo->exeUpdate($upSql);
                   
            }
        //exit();
        }

        
		$sql="delete from $plugin_table where name='$path'";
		$sql2="delete from $t_plugin_url where name='$path'";
		if($dbo->exeUpdate($sql)!==false && $dbo->exeUpdate($sql2)!==false){
			comp_plugins_position();
			$key_mt='plugin/list/id_mt';
			updateCache($key_mt);			
			echo "<div style='color:green'>".$pl_langpackage->pl_uninstall_successful."</div>";
		}
	}
}
?>
</div>
</body>
</html>