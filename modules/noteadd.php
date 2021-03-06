<?php

/*
 * LMS version 1.11-git
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

//$taxeslist = $LMS->GetTaxes();

$SESSION->restore('notecontents', $contents);
$SESSION->restore('notecustomer', $customer);
$SESSION->restore('note', $note);
$SESSION->restore('notenewerror', $error);

$action = isset($_GET['action']) ? $_GET['action'] : NULL;

switch($action)
{
	case 'init':

    		unset($note);
    		unset($contents);
    		unset($customer);
    		unset($error);

		// get default note's numberplanid and next number
		$note['cdate'] = time();
		$note['paytime'] = $CONFIG['notes']['paytime'];
		if (isset($_GET['contractor'])) $note['contractor'] = true; else $note['contractor'] = false;
		if(isset($_GET['customerid']) && $_GET['customerid'] != '' && ($LMS->CustomerExists($_GET['customerid']) || $LMS->ContractorExists($_GET['customerid'])) )
		{
		    if ($note['contractor'])
			$customer = $LMS->GetContractor($_GET['customerid']);
		else
			$customer = $LMS->GetCustomer($_GET['customerid'], true);
							
			$note['numberplanid'] = $DB->GetOne('SELECT n.id FROM numberplans n
				JOIN numberplanassignments a ON (n.id = a.planid)
				WHERE n.doctype = ? AND n.isdefault = 1 AND a.divisionid = ?',
				array(DOC_DNOTE, $customer['divisionid']));
		}
	
		if(empty($note['numberplanid']))
			$note['numberplanid'] = $DB->GetOne('SELECT id FROM numberplans
				WHERE doctype = ? AND isdefault = 1', array(DOC_DNOTE));
	break;

	case 'additem':

		$itemdata = r_trim($_POST);

		$itemdata['value'] = f_round($itemdata['value']);
		$itemdata['description'] = $itemdata['description'];
		
		if ($itemdata['value'] > 0 && $itemdata['description'] != '')		
		{
			$itemdata['posuid'] = (string) getmicrotime();
			$contents[] = $itemdata;
		}
	break;

	case 'deletepos':
		if(sizeof($contents))
			foreach($contents as $idx => $row)
				if($row['posuid'] == $_GET['posuid']) 
					unset($contents[$idx]);
	break;

	case 'setcustomer':

		unset($note); 
		unset($customer);
		unset($error);
		
		if($note = $_POST['note'])
			foreach($note as $key => $val)
				$note[$key] = $val;
		
		$note['customerid'] = $_POST['customerid'];
		
		if (isset($_POST['contractor']) && !empty($_POST['contractor'])) 
			$note['contractor'] = TRUE;
		else
			$note['contractor'] = FALSE;

		if($note['cdate'])
		{
			list($year, $month, $day) = explode('/', $note['cdate']);
			if(checkdate($month, $day, $year)) 
			{
				$note['cdate'] = mktime(date('G',time()),date('i',time()),date('s',time()),$month,$day,$year);
				$currmonth = $month;
			}
			else
			{
				$error['cdate'] = trans('Incorrect date format!');
				$note['cdate'] = time();
				break;
			}
		}

		if($note['cdate'] && !isset($note['cdatewarning']))
		{
			$maxdate = $DB->GetOne('SELECT MAX(cdate) FROM documents WHERE type = ? AND numberplanid = ?', 
					array(DOC_DNOTE, $note['numberplanid']));
	
			if($note['cdate'] < $maxdate)
			{
				$error['cdate'] = trans('Last date of debit note settlement is $a. If sure, you want to write note with date of $b, then click "Submit" again.', date('Y/m/d H:i', $maxdate), date('Y/m/d H:i', $note['cdate']));
				$note['cdatewarning'] = 1;
			}
		}
		elseif(!$note['cdate'])
			$note['cdate'] = time();

		if($note['number'])
		{
			if(!preg_match('/^[0-9]+$/', $note['number']))
				$error['number'] = trans('Debit note number must be integer!');
			elseif($LMS->DocumentExists($note['number'], DOC_DNOTE, $note['numberplanid'], $note['cdate']))
				$error['number'] = trans('Debit note number $a already exists!', $note['number']);
		}

		if(empty($note['paytime_default']) && !preg_match('/^[0-9]+$/', $note['paytime']))
                {
		        $error['paytime'] = trans('Integer value required!');
		}

		if(!isset($error))
		{
			$cid = isset($_GET['customerid']) && $_GET['customerid'] != '' ? intval($_GET['customerid']) : intval($_POST['customerid']);
			
			if ($note['contractor']) {
			    if($LMS->ContractorExists($cid))
				$customer = $LMS->GetContractor($cid, true);
			} else {
			    if($LMS->CustomerExists($cid))
				$customer = $LMS->GetCustomer($cid, true);
			}
			
			// finally check if selected customer can use selected numberplan
			if($note['numberplanid'] && isset($customer))
				if(!$DB->GetOne('SELECT 1 FROM numberplanassignments
					WHERE planid = ? AND divisionid = ?', array($note['numberplanid'], $customer['divisionid'])))
				{
					$error['number'] = trans('Selected numbering plan doesn\'t match customer\'s division!');
					unset($customer);
				}
		}
	break;

	case 'save':

		if($contents && $customer)
		{
			$DB->BeginTrans();
			$DB->LockTables(array('documents', 'cash', 'debitnotecontents', 'numberplans', 'divisions'));

			if(!$note['number'])
				$note['number'] = $LMS->GetNewDocumentNumber(DOC_DNOTE, $note['numberplanid'], $note['cdate']);
			else
			{
				if(!preg_match('/^[0-9]+$/', $note['number']))
					$error['number'] = trans('Debit note number must be integer!');
				elseif($LMS->DocumentExists($note['number'], DOC_DNOTE, $note['numberplanid'], $note['cdate']))
					$error['number'] = trans('Debit note number $a already exists!', $note['number']);
				
				if($error)
					$note['number'] = $LMS->GetNewDocumentNumber(DOC_DNOTE, $note['numberplanid'], $note['cdate']);
			}
			
			// set paytime
			if(!empty($note['paytime_default']))
			{
			        if($customer['paytime'] != -1)
			                $note['paytime'] = $customer['paytime'];
			        elseif(($paytime = $DB->GetOne('SELECT inv_paytime FROM divisions
			                WHERE id = ?', array($customer['divisionid']))) !== NULL)
				        $note['paytime'] = $paytime;
				else
				        $note['paytime'] = $CONFIG['notes']['paytime'];
			}

			$cdate = !empty($note['cdate']) ? $note['cdate'] : time();
			
			$division = $DB->GetRow('SELECT name, shortname, address, city, zip, countryid, ten, regon,
				account, inv_header, inv_footer, inv_author, inv_cplace 
				FROM divisions WHERE id = ? ;',array($customer['divisionid']));

			if ($note['numberplanid'])
				$fullnumber = docnumber($note['number'],
					$DB->GetOne('SELECT template FROM numberplans WHERE id = ?', array($note['numberplanid'])),
					$cdate);
			else
				$fullnumber = null;
			
			$DB->Execute('INSERT INTO documents (number, numberplanid, type,
		                        cdate, userid, customerid, name, address, paytime,
		                        ten, ssn, zip, city, countryid, divisionid,
		                        div_name, div_shortname, div_address, div_city, div_zip, div_countryid,
		                        div_ten, div_regon, div_account, div_inv_header, div_inv_footer, div_inv_author, div_inv_cplace, fullnumber)
		                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
		                        array($note['number'],
		                                !empty($note['numberplanid']) ? $note['numberplanid'] : 0,
		                                DOC_DNOTE,
		                                $cdate,
		                                $AUTH->id,
		                                $customer['id'],
				                $customer['customername'],
				                $customer['address'],
						$note['paytime'],
				                $customer['ten'],
				                $customer['ssn'],
				                $customer['zip'],
				                $customer['city'],
				                $customer['countryid'],
				                $customer['divisionid'],
				                ($division['name'] ? $division['name'] : ''),
				                ($division['shortname'] ? $division['shortname'] : ''),
				                ($division['address'] ? $division['address'] : ''),
				                ($division['city'] ? $division['city'] : ''),
				                ($division['zip'] ? $division['zip'] : ''),
				                ($division['countryid'] ? $division['countryid'] : 0),
				                ($division['ten'] ? $division['ten'] : ''),
				                ($division['regon'] ? $division['regon'] : ''),
				                ($division['account'] ? $division['account'] : ''),
				                ($division['inv_header'] ? $division['inv_header'] : ''),
				                ($division['inv_footer'] ? $division['inv_footer'] : ''),
				                ($division['inv_author'] ? $division['inv_author'] : ''),
				                ($division['inv_cplace'] ? $division['inv_cplace'] : ''),
				                ($fullnumber ? $fullnumber : NULL),
					));
			
			$nid = $DB->GetLastInsertID('documents');
			
			$itemid=0;
            		foreach($contents as $idx => $item)
		        {
			        $itemid++;
				$item['value'] = str_replace(',','.', $item['value']);

				$DB->Execute('INSERT INTO debitnotecontents (docid, itemid, value, description)
					VALUES (?, ?, ?, ?)', array($nid, $itemid, $item['value'], $item['description']));

				$LMS->AddBalance(array(
				        'time' => $cdate,
				        'value' => $item['value']*-1,
				        'taxid' => 0,
				        'customerid' => $customer['id'],
				        'comment' => $item['description'],
				        'docid' => $nid,
				        'itemid'=> $itemid
				));
			}

			$DB->UnLockTables();
			$DB->CommitTrans();
			
			$SESSION->remove('notecontents');
			$SESSION->remove('notecustomer');
			$SESSION->remove('note');
			$SESSION->remove('notenewerror');
			
			if(isset($_GET['print']))
				$SESSION->save('noteprint', $nid);

			$SESSION->redirect('?m=noteadd&action=init');
		}
	break;
}

$SESSION->save('note', $note);
$SESSION->save('notecontents', isset($contents) ? $contents : NULL);
$SESSION->save('notecustomer', isset($customer) ? $customer : NULL);
$SESSION->save('notenewerror', isset($error) ? $error : NULL);

if($action)
{
	// redirect needed because we don't want to destroy contents of note in order of page refresh
	$SESSION->redirect('?m=noteadd');
}

if(!isset($CONFIG['phpui']['big_networks']) || !chkconfig($CONFIG['phpui']['big_networks']))
{
        $SMARTY->assign('customers', $LMS->GetCustomerNames());
}

if($newnote = $SESSION->get('noteprint'))
{
        $SMARTY->assign('newnoteid', $newnote);
        $SESSION->remove('noteprint');
}

$layout['pagetitle'] = trans('New Debit Note<!long>');

$SMARTY->assign('error', $error);
$SMARTY->assign('contents', $contents);
$SMARTY->assign('customer', $customer);
$SMARTY->assign('note', $note);
$SMARTY->assign('numberplanlist', $LMS->GetNumberPlans(DOC_DNOTE, date('Y/m', $note['cdate'])));
//$SMARTY->assign('taxeslist', $taxeslist);
$SMARTY->display('noteadd.html');

?>
