<?php
require_once("config.php");
require_once(SQL_LIB_DIR . "do_not_loan.class.php");

$name = $_REQUEST['name'];
$ssn = $_REQUEST['ssn'];
$company_id = $_REQUEST['company_id'];

$ssn_wk = trim(str_replace('-', '', $ssn));

$db = ECash::getMasterDb();

$dnl = new Do_Not_Loan($db);
$does_current_exists = $dnl->Does_SSN_In_Table_For_Company($ssn_wk, $company_id);
$does_other_exists = $dnl->Does_SSN_In_Table_For_Other_Company($ssn_wk, $company_id);
$does_override_exists = $dnl->Does_Override_Exists_For_Company($ssn_wk, $company_id);
$dnl_info = $dnl->Get_DNL_Info_For_Override_Window($ssn_wk);

if($does_current_exists)
{
	$ind = 0;
	foreach($dnl_info as $key => $value)
	{
		if($dnl_info[$key]->company_id == $company_id)
		{
			$ind = $key;
			break;
		}
	}

	$comp_id = $dnl_info[$ind]->company_id;			
	$category = $dnl_info[$ind]->name;
	$explanation = $dnl_info[$ind]->explanation;
	$agent_id = $dnl_info[$ind]->agent_id;
	$date_created = $dnl_info[$ind]->date_created;

	unset($dnl_info[$ind]);
}

$html_before_title_alt = " 	<tr class=\"height\">
				<td class=\"align_left_alt_bold\" width=\"30%\">&nbsp;
			";

$html_after_title_alt = " 					&nbsp;</td>
				<td class=\"align_left_alt\" width=\"5%\">&nbsp;</td>
				<td class=\"align_left_alt\" width=\"65%\">	
			";

$html_before_title = 	" 	<tr class=\"height\">
				<td class=\"align_left_bold\" width=\"30%\">&nbsp;
			";

$html_after_title = 	" 					&nbsp;</td>
				<td class=\"align_left\" width=\"5%\">&nbsp;</td>
				<td class=\"align_left\" width=\"65%\">	
			";


$html_space_alt = "	<tr class=\"height\">
			<td class=\"align_left_alt_bold\" width=\"30%\">&nbsp;&nbsp;</td>
			<td class=\"align_left_alt\" width=\"5%\">&nbsp;</td>
			<td class=\"align_left_alt\" width=\"65%\"></td></tr>
		";

$html_space = 	"	<tr class=\"height\">
			<td class=\"align_left_bold\" width=\"30%\">&nbsp;&nbsp;</td>
			<td class=\"align_left\" width=\"5%\">&nbsp;</td>
			<td class=\"align_left\" width=\"65%\"></td></tr>
		";

$html_current = "	<tr class=\"height\" bgcolor=\"#FFEFD5\">
				<td class=\"align_left_bold\" width=\"30%\">Current</td>
				<td width=\"5%\"><nobr>&nbsp;</nobr></td>
				<td class=\"align_right\" width=\"65%\"><input type=\"button\" value=\"Remove DNL\" class=\"button\" onClick=\"javascript:Remove_DNL();\"></td>
			</tr>
		";

$html_other = "		<tr class=\"height\" bgcolor=\"#FFEFD5\">
				<td class=\"align_left_bold\" width=\"30%\">Other Companies</td>
				<td width=\"5%\"><nobr>&nbsp;</nobr></td>
				<td width=\"65%\"><nobr></nobr></td>
			</tr>
		";

$html_override = "	<tr class=\"height\">
				<td class=\"align_left\"><input type=\"button\" value=\"Override DNL\" class=\"button\" onClick=\"javascript:Override_DNL();\"></td>
			</tr>
		";

$html_remove_override = "	<tr class=\"height\">
				<td class=\"align_left\"><input type=\"button\" value=\"Remove Override DNL\" class=\"button\" onClick=\"javascript:Remove_Override_DNL();\"></td>
				</tr>
			";
?>

<html>
<head>
<link rel="stylesheet" href="css/style.css">
<script type="text/javascript" src="js/layout.js"></script>
<title>DNL Override</title>

<script type="text/javascript">

function Remove_DNL()
{	
	document.getElementById('do_not_loan_action').value = "remove_do_not_loan";
	document.getElementById('do_not_loan_form').submit();
}

function Override_DNL()
{	
	document.getElementById('do_not_loan_action').value = "override_do_not_loan";
	document.getElementById('do_not_loan_form').submit();
}

function Remove_Override_DNL()
{	
	document.getElementById('do_not_loan_action').value = "remove_override_do_not_loan";
	document.getElementById('do_not_loan_form').submit();
}

</script>

</head>
<body class="bg" onload="self.focus();">
<table cellpadding=0 cellspacing=0 width="100%">
		<tr>
			<td class="border" align="left" valign="top">
				<table cellpadding=0 cellspacing=0 width="100%">
					<?php 
					if($does_current_exists)
					{
						echo $html_current;
						echo ($html_before_title_alt . "Name on Account:" . $html_after_title_alt . $name . "</td></tr>");
						echo ($html_before_title . "SSN:" . $html_after_title . $ssn . "</td></tr>");
						echo ($html_before_title_alt . "DNL Category:" . $html_after_title_alt . ucwords(str_replace('_', ' ', $category)) . "</td></tr>");
						echo ($html_before_title . "DNL Explanation:" . $html_after_title . $explanation . "</td></tr>");
						echo ($html_before_title_alt . "Agent ID:" . $html_after_title_alt . $agent_id . "</td></tr>");
						echo ($html_before_title . "DNL Set Date:" . $html_after_title . $date_created . "</td></tr>");
						echo $html_space_alt . $html_space;
					}
					
					if($does_other_exists)
					{ 
						echo $html_other;
						foreach($dnl_info as $key => $value)
						{
							echo ($html_before_title_alt . "Company:" . $html_after_title_alt . $dnl_info[$key]->comp_name . "</td></tr>");
							echo ($html_before_title . "Name on Account:" . $html_after_title . $name . "</td></tr>");
							echo ($html_before_title_alt . "DNL Category:" . $html_after_title_alt . ucwords(str_replace('_', ' ', $dnl_info[$key]->name)) . "</td></tr>");
							echo ($html_before_title . "DNL Explanation:" . $html_after_title . $dnl_info[$key]->explanation . "</td></tr>");
							echo ($html_before_title_alt . "Agent ID:" . $html_after_title_alt . $dnl_info[$key]->agent_id . "</td></tr>");
							echo ($html_before_title . "DNL Set Date:" . $html_after_title . $dnl_info[$key]->date_created . "</td></tr>");
							echo $html_space_alt . $html_space;
						}
						
						if($does_override_exists)
							echo $html_remove_override;
						else
							echo $html_override;
					}
					?>
				</table>
			</td>
		</tr>
	</table>

	<form id="do_not_loan_form" method="post" action="/" class="no_padding">
	<input type="hidden" name="action" id="do_not_loan_action" value="">
	<input type="hidden" name="application_id" value="<?php echo $_REQUEST['application_id']; ?>">
	</form>
</body>
</html>
