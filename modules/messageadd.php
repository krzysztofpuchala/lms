<?php

/*
 * iNET LMS  version 1.0.2
 *
 *  (C) Copyright 2001-2012 LMS Developers
 *
 *  Please, see the doc/AUTHORS for more information about authors!
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License Version 2 as
 *  published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307,
 *  USA.
 *
 *  $Id$
 */

function selecttemplates($idtheme,$idfield,$idtemplate)
{
    global $DB;
    $template = $DB->GetRow('SELECT theme, message FROM messagestemplate WHERE id = ? '.$DB->limit(1).' ;',array(intval($idtemplate)));
    if (!$template) $template['theme'] = $template['message'] = "";
    $obj = new xajaxResponse();
    $obj->assign($idtheme,'value',$template['theme']);
    $obj->assign($idfield,'value',$template['message']);
    return $obj;
}

function GetRecipients($filter, $type=MSG_MAIL)
{
	global $LMS;

	$group = $filter['group'];
	$network = $filter['network'];
	$customergroup = $filter['customergroup'];
	$nodegroup = $filter['nodegroup'];
	$linktype = $filter['linktype'];

	if($group == 4)
	{
		$deleted = 1;
		$network = NULL;
		$customergroup = NULL;
	}
	else
		$deleted = 0;

	$disabled = ($group == 5) ? 1 : 0;
	$indebted = ($group == 6) ? 1 : 0;
	$notindebted = ($group == 7) ? 1 : 0;

	if($group>3) $group = 0;

	if($network)
		$net = $LMS->GetNetworkParams($network);

	if($type == MSG_SMS)
	{
		$smstable = 'JOIN (SELECT MIN(phone) AS phone, customerid
				FROM customercontacts
				WHERE (type & '.CONTACT_MOBILE.') = '.CONTACT_MOBILE.'
				GROUP BY customerid
			) x ON (x.customerid = c.id)';
	}
	
	if ($type == MSG_GADUGADU)
	{
	    $gadutable = 'JOIN (SELECT uid AS gadugadu, customerid FROM imessengers WHERE type=\'0\' GROUP BY uid,customerid) x ON (x.customerid = c.id) ';
	}

	$recipients = $LMS->DB->GetAll('SELECT c.id, email, pin, '
		.($type==MSG_SMS ? 'x.phone, ': '')
		.($type==MSG_GADUGADU ? 'x.gadugadu, ' : '')
		.$LMS->DB->Concat('c.lastname', "' '", 'c.name').' AS customername, '
		.$LMS->DB->Concat('c.address', "' '", 'c.zip',"' '",'c.city').' AS address, '
		.$LMS->DB->Concat('c.post_address', "' '", 'c.post_zip',"' '",'c.post_city').' AS postaddress, '
		.'COALESCE(b.value, 0) AS balance
		FROM customersview c 
		LEFT JOIN (
			SELECT SUM(value) AS value, customerid
			FROM cash GROUP BY customerid
		) b ON (b.customerid = c.id) '
		.(!empty($smstable) ? $smstable : '')
		.(!empty($gadutable) ? $gadutable : '')
		.'WHERE deleted = '.$deleted
		.($type == MSG_MAIL ? ' AND email != \'\'' : '')
		.($group!=0 ? ' AND status = '.$group : '')
		.($network ? ' AND c.id IN (SELECT ownerid FROM nodes WHERE 
			(ipaddr > '.$net['address'].' AND ipaddr < '.$net['broadcast'].') 
			OR (ipaddr_pub > '.$net['address'].' AND ipaddr_pub < '.$net['broadcast'].'))' : '')
		.($customergroup ? ' AND c.id IN (SELECT customerid FROM customerassignments
			WHERE customergroupid = '.intval($customergroup).')' : '')
		.($nodegroup ? ' AND c.id IN (SELECT ownerid FROM nodes
			JOIN nodegroupassignments ON (nodeid = nodes.id)
			WHERE nodegroupid = '.intval($nodegroup).')' : '')
		.($linktype != '' ? ' AND c.id IN (SELECT ownerid FROM nodes
			WHERE linktype = '.intval($linktype).')' : '')
		.($disabled ? ' AND EXISTS (SELECT 1 FROM nodes WHERE ownerid = c.id
			GROUP BY ownerid HAVING (SUM(access) != COUNT(access)))' : '')
		.($indebted ? ' AND COALESCE(b.value, 0) < 0' : '')
		.($notindebted ? ' AND COALESCE(b.value, 0) >= 0' : '')
		.' ORDER BY customername');

	return $recipients;
}

function GetRecipient($customerid, $type=MSG_MAIL)
{
	global $LMS;

	if($type == MSG_SMS)
	{
		$smstable = 'JOIN (SELECT phone, customerid
				FROM customercontacts 
				WHERE customerid = '.$customerid.'
				    AND (type & '.CONTACT_MOBILE.') = '.CONTACT_MOBILE.'
				ORDER BY phone LIMIT 1
			) x ON (x.customerid = c.id)';
	}
	
	if ($type == MSG_GADUGADU)
	{
	    $gadutable = 'JOIN (SELECT uid AS gadugadu, customerid FROM imessengers WHERE type=\'0\' GROUP BY uid,customerid) x ON (x.customerid = c.id) ';
	}

	return $LMS->DB->GetAll('SELECT c.id, email, pin, '
		.($type==MSG_SMS ? 'x.phone, ': '')
		.($type==MSG_GADUGADU ? 'x.gadugadu, ' : '')
		.$LMS->DB->Concat('c.lastname', "' '", 'c.name').' AS customername,
		COALESCE((SELECT SUM(value) FROM cash WHERE customerid = c.id), 0) AS balance
		FROM customersview c '
		.(!empty($smstable) ? $smstable : '')
		.(!empty($gadutable) ? $gadutable : '')
		.'WHERE c.id = '.$customerid
		.($type == MSG_MAIL ? ' AND email != \'\'' : ''));
}

function BodyVars(&$body, $data)
{
	global $LMS, $LANGDEFS;
	
	    if (!empty($data['customername'])) 
		$body = str_replace('%customer', $data['customername'], $body);
	    else
		$body = str_replace('%customer','', $body);
	    
	    if (!empty($data['balance'])) 
		$body = str_replace('%balance', $data['balance'], $body);
	    else
		$body = str_replace('%balance','', $body);
	
	    if (!empty($data['id'])) 
		$body = str_replace('%cid', $data['id'], $body);
	    else
		$body = str_replace('%cid','', $body);
		
	    if (!empty($data['pin'])) 
		$body = str_replace('%pin', $data['pin'], $body);
	    else
		$body = str_replace('%pin', '', $body);
	    
	    if (!empty($data['address'])) 
		$body = str_replace('%address', $data['address'], $body);
	    else
		$body = str_replace('%address','', $body);
	    
	    if (!empty($data['postaddress'])) 
		$body = str_replace('%postaddress', $data['postaddress'], $body);
	    else
		$body = str_replace('%postaddress','', $body);

	    if (bankaccount($data['id'])!='') 
	    {
		if (strpos($body, '%bankaccount') !== false)
		$body = str_replace('%bankaccount', format_bankaccount(bankaccount($data['id'])), $body);
	    }
	    else
		$body = str_replace('%bankaccount','', $body);
	
	
	if(!(strpos($body, '%last_3_in_a_table') === FALSE))
	{
		$last3 = '';
		if($last3_array = $LMS->DB->GetAll('SELECT comment, time, value 
			FROM cash WHERE customerid = ?
			ORDER BY time DESC LIMIT 3', array($data['id'])))
		{
			foreach($last3_array as $r)
			{
				$last3 .= date("Y/m/d | ", $r['time']);
				$last3 .= sprintf("%20s | ", sprintf($LANGDEFS[$LMS->ui_lang]['money_format'], $r['value']));
				$last3 .= $r['comment']."\n";
			}
		}
		$body = str_replace('%last_3_in_a_table', $last3, $body);
	}

	if(!(strpos($body, '%last_10_in_a_table') === FALSE))
	{
		$last10 = '';
		if($last10_array = $LMS->DB->GetAll('SELECT comment, time, value 
			FROM cash WHERE customerid = ?
			ORDER BY time DESC LIMIT 10', array($data['id'])))
		{
			foreach($last10_array as $r)
			{
				$last10 .= date("Y/m/d | ", $r['time']);
				$last10 .= sprintf("%20s | ", sprintf($LANGDEFS[$LMS->ui_lang]['money_format'], $r['value']));
				$last10 .= $r['comment']."\n";
			}
		}
		$body = str_replace('%last_10_in_a_table', $last10, $body);
	}
}

$layout['pagetitle'] = trans('Message Add');

if(isset($_POST['message']))
{
	$message = $_POST['message'];

//	$message['type'] = $message['type'] == MSG_MAIL ? MSG_MAIL : ($message['type'] == MSG_SMS ? MSG_SMS : MSG_ANYSMS);

	if(empty($message['customerid']) && ($message['group'] < 0 || $message['group'] > 7))
		$error['group'] = trans('Incorrect customers group!');

	if($message['type'] == MSG_MAIL)
	{
		$message['body'] = $message['mailbody'];

		if($message['sender']=='')
			$error['sender'] = trans('Sender e-mail is required!');
		elseif(!check_email($message['sender']))
			$error['sender'] = trans('Specified e-mail is not correct!');

		if($message['from']=='')
			$error['from'] = trans('Sender name is required!');
	}
	if ($message['type'] == MSG_SMS || $message['type'] == MSG_ANYSMS)
	{
		$message['body'] = $message['smsbody'];
		$message['sender'] = '';
		$message['from'] = '';
		$phonenumbers = array();
		if ($message['type'] == MSG_ANYSMS)
		{
			$message['phonenumber'] = preg_replace('/[ \t]/', '', $message['phonenumber']);
			if (preg_match('/^[\+]?[0-9]+(,[\+]?[0-9]+)*$/', $message['phonenumber']))
				$phonenumbers = preg_split('/,/', $message['phonenumber']);
			if (count($message['users']))
				$phonenumbers = array_merge($phonenumbers, $message['users']);
			if (empty($phonenumbers))
				$error['phonenumber'] = trans('Specified phone number is not correct!');
		}
	}
	if ($message['type'] == MSG_GADUGADU)
	{
	    $message['body'] = $message['gadugadubody'];
	    $message['sender'] = '';
	    $message['from'] = '';
	}
	
	if ($message['type'] == MSG_USERPANEL)
	{
		$message['body'] = $message['userpanelbody'];
		$message['sender']='';
		$message['from']='';
	}

	if($message['subject']=='')
		$error['subject'] = trans('Message subject is required!');

	if($message['body']=='')
		$error['body'] = trans('Message body is required!');

	if($filename = $_FILES['file']['name'])
	{
		if(is_uploaded_file($_FILES['file']['tmp_name']) && $_FILES['file']['size'])
		{
			$file = '';
			$fd = fopen($_FILES['file']['tmp_name'], 'r');
			if($fd)
			{
				while(!feof($fd))
					$file .= fread($fd,256);
				fclose($fd);
			}
		} 
		else // upload errors
			switch($_FILES['file']['error'])
			{
				case 1:
				case 2: $error['file'] = trans('File is too large.'); break;
				case 3: $error['file'] = trans('File upload has finished prematurely.'); break;
				case 4: $error['file'] = trans('Path to file was not specified.'); break;
				default: $error['file'] = trans('Problem during file upload.'); break;
			}
	}

	if(!$error)
	{
		$recipients = array();
		if(empty($message['customerid']))
			if ($message['type'] == MSG_SMS || $message['type'] == MSG_MAIL || $message['type'] == MSG_GADUGADU || $message['type'] == MSG_USERPANEL)
				$recipients = GetRecipients($message, $message['type']);
			else
				foreach($phonenumbers as $phone)
					$recipients[]['phone'] = $phone;
		else
			$recipients = GetRecipient($message['customerid'], $message['type']);

		if(!$recipients)
			$error['subject'] = trans('Unable to send message. No recipients selected!');
	}

	if(!$error)
	{
		set_time_limit(0);

		$message['body'] = str_replace("\r", '', $message['body']);

		if($message['type'] == MSG_MAIL)
			$message['body'] = wordwrap($message['body'],76,"\n");

		$SMARTY->assign('message', $message);
		$SMARTY->assign('recipcount', sizeof($recipients));
		$SMARTY->display('messagesend.html');

		$DB->BeginTrans();
		
//		if ($message['type'] == MSG_USERPANEL) $messbody = base64_encode($message['body']); else $messbody = $message['body'];

		$DB->Execute('INSERT INTO messages (type, cdate, subject, body, userid, sender)
			VALUES (?, ?NOW?, ?, ?, ?, ?)', array(
				$message['type'],
				$message['subject'],
				$message['body'],
				$AUTH->id,
				$message['type']==MSG_MAIL ? '"'.$message['from'].'" <'.$message['sender'].'>' : '',
			));

		$msgid = $DB->GetLastInsertID('messages');

		foreach($recipients as $key => $row)
		{
			if($message['type'] == MSG_MAIL) $recipients[$key]['destination'] = $row['email'];
			if($message['type'] == MSG_SMS || $message['type'] == MSG_ANYSMS) $recipients[$key]['destination'] = $row['phone'];
			if($message['type'] == MSG_GADUGADU) $recipients[$key]['destination'] = $row['gadugadu'];
			if($message['type'] == MSG_USERPANEL) $recipients[$key]['destination'] = $row['id'];

			$DB->Execute('INSERT INTO messageitems (messageid, customerid,
				destination, status)
				VALUES (?, ?, ?, ?)', array(
					$msgid,
					isset($row['id']) ? $row['id'] : 0,
					$recipients[$key]['destination'],
					MSG_NEW,
				));
		}

		$DB->CommitTrans();

		if($message['type'] == MSG_MAIL)
		{
			$files = NULL;
			if (isset($file))
			{
				$files[0]['content_type'] = $_FILES['file']['type'];
				$files[0]['filename'] = $filename;
				$files[0]['data'] = $file;
			}

			if(!empty($CONFIG['mail']['debug_email']))
				echo '<B>'.trans('Warning! Debug mode (using address $a).',$CONFIG['mail']['debug_email']).'</B><BR>';

			$headers['From'] = '"'.$message['from'].'" <'.$message['sender'].'>';
			$headers['Subject'] = $message['subject'];
			$headers['Reply-To'] = $headers['From'];
		}
		elseif ($message['type'] == MSG_SMS || $message['type'] == MSG_ANYSMS)
		{
			if (!empty($CONFIG['sms']['debug_phone']))
				echo '<B>'.trans('Warning! Debug mode (using phone $a).',$CONFIG['sms']['debug_phone']).'</B><BR>';
		}
		elseif ($message['type'] == MSG_GADUGADU)
		{
		    if (!empty($CONFIG['gadugadu']['gg_number']))
				echo '<b>UWAGA! Wysyłanie wiadomości z numeru '.$CONFIG['gadugadu']['gg_number'].'</b><br>';
		}
		
		if ($message['type'] == MSG_GADUGADU)
		    $gg_connect = $LMS->ConnectGaduGadu();
		else
		    $gg_connect = FALSE;

		foreach($recipients as $key => $row)
		{
			$body = $message['body'];

			BodyVars($body, $row);

			if($message['type'] == MSG_MAIL)
			{
				$headers['To'] = '<'.$row['destination'].'>';
				echo '<img src="img/mail.gif" border="0" align="absmiddle" alt=""> ';
			}
			elseif($message['type'] == MSG_SMS || $message['type'] == MSG_ANYSMS)
			{
				$row['destination'] = preg_replace('/[^0-9]/', '', $row['destination']);
				echo '<img src="img/sms.gif" border="0" align="absmiddle" alt=""> ';
			}
			elseif($message['type'] == MSG_GADUGADU)
			{
				$row['destination'] = preg_replace('/[^0-9]/', '', $row['destination']);
				echo '<img src="img/gg.gif" border="0" align="absmiddle" alt=""> ';
			}
			else
			{
				$row['destination'] = preg_replace('/[^0-9]/', '', $row['destination']);
				echo '<img src="img/cms.gif" border="0" align="absmiddle" alt=""> ';
			}

			echo trans('$a of $b ($c) $d:', ($key+1), sizeof($recipients),
				sprintf('%02.1f%%',round((100/sizeof($recipients))*($key+1),1)),
				$row['customername'].' &lt;'.$row['destination'].'&gt;');
			flush();

			if($message['type'] == MSG_MAIL) $result = $LMS->SendMail($row['destination'], $headers, $body, $files);
			elseif($message['type'] == MSG_SMS || $message['type'] == MSG_ANYSMS) $result = $LMS->SendSMS($row['destination'], $body, $msgid);
			elseif($message['type'] == MSG_GADUGADU) $result = $LMS->SendGaduGadu($row['destination'], $body, $gg_connect);
			elseif($message['type'] == MSG_USERPANEL) $result = MSG_SENT;

			if (is_string($result))
				echo " <font color=red>$result</font>";
			else if ($result == MSG_SENT)
				echo ' ['.trans('sent').']';
			else 
				echo ' ['.trans('added').']';

			echo "<BR>\n";

			if (!is_int($result) || $result == MSG_SENT)
				$DB->Execute('UPDATE messageitems SET status = ?, lastdate = ?NOW?,
					error = ? WHERE messageid = ? AND customerid = ?',
					array(
						is_int($result) ? $result : MSG_ERROR,
						is_int($result) ? null : $result,
						$msgid,
						$row['id'],
					));
		}
		
		if ($gg_connect) $LMS->DisConnectGaduGadu($gg_connect);
		$SMARTY->display('footer.html');
		$SESSION->close();
		die;
	}
	else if (!empty($message['customerid']))
	{
		$message['customer'] = $DB->GetOne('SELECT '
			.$DB->Concat('UPPER(lastname)',"' '",'name').'
			FROM customersview
			WHERE id = ?', array($message['customerid']));
	}

	$SMARTY->assign('error', $error);
	$SMARTY->assign('message', $message);
}
else if (!empty($_GET['customerid']))
{
	$message = $DB->GetRow('SELECT id AS customerid, '
		.$DB->Concat('UPPER(lastname)',"' '",'name').' AS customer
		FROM customersview
		WHERE id = ?', array($_GET['customerid']));

	$SMARTY->assign('message', $message);
}

$LMS->InitXajax();
$LMS->RegisterXajaxFunction('selecttemplates');
$SMARTY->assign('xajax',$LMS->RunXajax());


$SMARTY->assign('templatelist',$LMS->GetListThemeTemplate(false));
$SMARTY->assign('networks', $LMS->GetNetworks());
$SMARTY->assign('customergroups', $LMS->CustomergroupGetAll());
$SMARTY->assign('nodegroups', $LMS->GetNodeGroupNames());
$SMARTY->assign('userinfo', $LMS->GetUserInfo($AUTH->id));
$SMARTY->assign('users', $DB->GetAll('SELECT name, phone FROM users WHERE phone <> \'\' ORDER BY name'));
$SMARTY->display('messageadd.html');

?>
