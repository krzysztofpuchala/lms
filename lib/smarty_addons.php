<?php

/*
 *  iNET LMS version 1.0.3
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
 *  $Id:  2013/02/18 11:01:28 Sylwester Kondracki Exp $
 *
 */
 
 
function _smarty_function_title($params, &$template)
{
		if (isset($params['value']) && !empty($params['value'])) $value = $params['value']; else $value = NULL;
		if (isset($params['link']) && !empty($params['link'])) $link = $params['link']; else $link = NULL;
		if (isset($params['help']) && !empty($params['help'])) $help = $params['help']; else $help = NULL;
		$width = 100;
		if ($link) $width -= 1;
		if ($help) $widh -= 1;
		$return = '';
		if ($value) {
		    $return .= '<div class="title" style="margin-top:12px;">';
		    $return .= '<table width="100%" cellpadding="0" cellspacing="0">';
		    $return .= '<tr>';
		    $return .= '<td width="'.$width.'%" class="title_sm" style="vertical-align:middle;" align="left">'.$value.'</td>';
		    if ($link)
			$return .= '<td width="1%" nowrap style="font-weight:normal;">'.$link.'</td>';
//		    if ($help)
//			$return .= '<td width="1%" nowrap style="padding-left:6px;"><img src="img/help.png" width="20" height="20" onclick="help_popup(\''.$help.'\');" title="[ POMOC ]" style="cursor:pointer;border:0;"></td>';
		    $return .= '</tr></table>';
		    $return .= '</div>';
		}
		return $return;
}

function _smarty_function_help($params, &$template)
{
		if (isset($params['key']) && !empty($params['key'])) $key = $params['key']; else $val = NULL;
		$return = '';
		if ($key) {
		    
		    $return = '<img src="img/help.png" width="16" height="16" onclick="help_popup(\''.$key.'\');" title="[ POMOC ]" style="cursor:pointer;border:0;">';
		    
		}
		return $return;
}

function _smarty_block_box($params, $content, &$template, &$repeat)
{
    if (empty($content)) return NULL;
    
    $boxtitleclass = $boxcontentclass = '';
    $title 	= (isset($params['title']) ? $params['title'] : '');
    $boxwidth 	= (isset($params['width']) ? $params['width'] : '185px');
    $boxheight 	= (isset($params['height']) ? $params['height'] : '');
    $boxalign 	= (isset($params['align']) ? $params['align'] : '');
    $boxvalign 	= (isset($params['valign']) ? $params['valign'] : '');
    $boxshadow 	= (isset($params['shadow']) ? true : false);
    $boxid 	= (isset($params['id']) ? $params['id'] : NULL);
    $boxradius 	= (isset($params['radius']) ? true : false);
    $boxclass 	= (isset($params['class']) ? $params['class'] : '');
    $boxstyle 	= (isset($params['style']) ? $params['style'] : '');
    $boxlink 	= (isset($params['link']) ? $params['link'] : '');
    $contentstyle = (isset($params['contentstyle']) ? $params['contentstyle'] : '');

    $template->assignGlobal('boxtitle',$title);
    $template->assignGlobal('boxlink',$boxlink);
    $template->assignGlobal('boxcontent',$content);
    $template->assignGlobal('boxwidth',$boxwidth);
    $template->assignGlobal('boxheight',$boxheight);
    $template->assignGlobal('boxid',$boxid);
    $template->assignGlobal('boxalign',$boxalign);
    $template->assignGlobal('boxvalign',$boxvalign);
    $template->assignGlobal('boxradius',$boxradius);
    $template->assignGlobal('boxshadow',$boxshadow);
    $template->assignGlobal('boxclass',$boxclass);
    $template->assignGlobal('boxstyle',$boxstyle);
    $template->assignGlobal('contentstyle',$contentstyle);
    
    return $template->fetch('box.html');
}



$SMARTY->registerPlugin('function', 'title', '_smarty_function_title');
$SMARTY->registerPlugin('function', 'help', '_smarty_function_help');
$SMARTY->registerPlugin('block','box','_smarty_block_box');


?>
