<?php
function display_pb_forms($atts) {
	global $wpdb;

if(isset($_POST['pbx_action']) && $_POST['pbx_action']=="update_request"){

//UPDATE REQUEST
	$req_id=$_POST['pbx_req_id'];
	$anon=(isset($_POST['pbx_anon']) && $_POST['pbx_anon']=='on')? 1 : 0;	
	$notify=(isset($_POST['pbx_notify']) && $_POST['pbx_notify']=='on')? 1 : 0;
	if(isset($_POST['pbx_closed']) && $_POST['pbx_closed']=='on'){
		$closed=time();
		$active=2;
	$wpdb->update($wpdb->prefix.'pb_requests',array('anon'=>$anon,'closed'=>$closed,'notify'=>$notify,'active'=>$active),array('id'=>$req_id));
	}else{
	$wpdb->update($wpdb->prefix.'pb_requests',array('anon'=>$anon,'notify'=>$notify),array('id'=>$req_id));
	}

	$updated_title=(isset($closed))? PB_REQ_CLOSED_TITLE : PB_REQ_UPDATED_TITLE;
	$updated_msg=(isset($closed))? PB_REQ_CLOSED_MSG : PB_REQ_UPDATED_MSG;
	
	$updated_request_output="<div id='praybox_wrapper'>";
	$updated_request_output.="<h2 class='pbx-title'>$updated_title</h2>";
	$updated_request_output.="<p class='pbx-text'>$updated_msg</p>";
	$updated_request_output.="</div>";	

	return $updated_request_output;

}elseif(isset($_POST['pbx_action']) && $_POST['pbx_action']=="submit_request"){
//Submit Request to DB, Email Mgmt Link, and Display a Message
	$first_name=(isset($_POST['pbx_first_name']) && $_POST['pbx_first_name']!="")? $_POST['pbx_first_name'] : "anon";
	$last_name=(isset($_POST['pbx_last_name']) && $_POST['pbx_last_name']!="")? $_POST['pbx_last_name'] : "anon";
	$anon=(isset($_POST['pbx_anon']) && $_POST['pbx_anon']=='on')? 1 : 0;	
	$email=$_POST['pbx_email'];	
	$authcode=rand_chars();
	$title=$_POST['pbx_title'];	
	$body=$_POST['pbx_body'];	
	$notify=(isset($_POST['pbx_notify']) && $_POST['pbx_notify']=='on')? 1 : 0;
	$ip_address=$_SERVER['REMOTE_ADDR'];
	$time_now=time();
	if(get_option('pb_admin_moderation')==1){$active=0;}else{$active=1;}

	//THROW FLAGS IF ANY OF THESE CONDITIONS ARE MET
	if((isIPBanned($ip_address)=="fail")||(isDuplicate($first_name,$last_name,$email,$title,$ip_address)=="fail")){$flaggit=1;}else{$flaggit=0;}

	//IF NO FLAGS, RUN IT
	if($flaggit==0){
		$site_name=get_bloginfo('name');
		
		$wpdb->insert($wpdb->prefix.'pb_requests',array('first_name'=>$first_name,'last_name'=>$last_name,'anon'=>$anon,'email'=>$email,'authcode'=>$authcode,'submitted'=>$time_now,'title'=>$title,'body'=>$body,'notify'=>$notify,'ip_address'=>$ip_address,'active'=>$active));
		
		$management_url=getManagementUrl($authcode);
		
	   	$email_from=get_option('pb_reply_to_email');
	   	$email_message=get_option('pb_email_prefix');
	   	$email_message.="\n\n".PB_REQ_EMAIL_MSG1." $management_url\n\n".PB_REQ_EMAIL_MSG2."\n\n";
	   	$email_message.=get_option('pb_email_suffix');
		$headers[]= "Reply-To:$site_name <{$email_from}>";
		$headers[]= "From:$site_name <{$email_from}>";
	   	
	   	wp_mail($email,PB_REQ_EMAIL_SUBJECT,$email_message,$headers);

		$submitted_output="<div id='praybox_wrapper'>";
		$submitted_output.="<h2 class='pbx-title'>".PB_REQ_SUBMITTED_TITLE."</h2>";
		$submitted_output.="<p class='pbx-text'>".PB_REQ_SUBMITTED_MSG."</p>";
		$submitted_output.="</div>";

	}else{

		$submitted_output="<div id='praybox_wrapper'>";
		$submitted_output.="<h2 class='pbx-title'>".PB_REQ_FAIL_TITLE."</h2>";
		$submitted_output.="<p class='pbx-text'>".PB_REQ_FAIL_MSG."</p><ul>";

		if(isDuplicate($first_name,$last_name,$email,$title,$ip_address)=="fail"){
			$submitted_output.="<li>".PB_REQ_FAIL_DUPLICATE."</li>";
		}
		if($_POST['pbx_required']!=""){
			$submitted_output.="<li>".PB_REQ_FAIL_SPAM."</li>";
		}
		if(isIPBanned($ip_address)=="fail"){
			$submitted_output.="<li>".PB_REQ_FAIL_BANNED."</li>";
		}
		$submitted_output.="</ul></div>";
	}

	return $submitted_output;

}else{

	if(!isset($_GET['pbid']) || $_GET['pbid']==""){
		$stat=0; //new request
		$anon="";
		$notify="";
		
		$sub_form_title=PB_FORM_TITLE;
		$sub_form_msg=get_option('PB_REQ_form_intro');
		$sub_form_action="submit_request";
		$sub_form_req_id_input="";
		$sub_form_submit=PB_FORM_SUBMIT;
	
	}else{
		$authcode=$_GET['pbid'];
		if(isRequestActive($authcode)=="yes"){
			$prayer_request=$wpdb->get_row("SELECT id,first_name,last_name,anon,email,title,body,notify FROM ".$wpdb->prefix."pb_requests WHERE authcode='$authcode'");
			
			$stat=1; //open request
			$anon=($prayer_request->anon==1)? "checked" : "";
			$notify=($prayer_request->notify==1)? "checked" : "";
	
			$sub_form_title=PB_FORM_EDIT_TITLE;
			$sub_form_msg=PB_FORM_EDIT_MSG;
			$sub_form_action="update_request";
			$sub_form_req_id_input="<input type='hidden' name='pbx_req_id' value='".$prayer_request->id."' />";
			$sub_form_submit=PB_FORM_EDIT_SUBMIT;
		}else{
			$stat=2; //request is closed
		}
	}

	$sub_form_output="<div id='praybox_wrapper'>";

	if($stat==2){
		//CLOSED REQUEST OUTPUT
		$sub_form_output.="<h2 class='pbx-title'>".PB_FORM_CLOSED_TITLE."</h2>";
		$sub_form_output.="<p class='pbx-text'>".PB_FORM_CLOSED_MSG."</p>";
	}else{
		//INITIAL SUBMISSION FORM OUTPUT
		$sub_form_output.="<h2 class='pbx-title'>$sub_form_title</h2>";
		$sub_form_output.="<p class='pbx-text'>$sub_form_msg</p>";
		$sub_form_output.="<form class='pbx-form' method='post'><input type='hidden' name='pbx_action' value='$sub_form_action' />$sub_form_req_id_input";
		$sub_form_output.=($stat==0)? "<div class='pbx-formfield'><label>".PB_FORM_FIRST_NAME.":</label><input type='text' name='pbx_first_name' /></div>" : "";
		$sub_form_output.=($stat==0)? "<div class='pbx-formfield'><label>".PB_FORM_LAST_NAME.":</label><input type='text' name='pbx_last_name' /></div>" : "";
		$sub_form_output.="<div class='pbx-formfield'><label><input type='checkbox' name='pbx_anon' $anon /> ".PB_FORM_ANONYMOUS."</label></div>";
		$sub_form_output.=($stat==0)? "<div class='pbx-formfield'><label>".PB_FORM_EMAIL.":</label><input type='text' name='pbx_email' /></div>" : "";
		$sub_form_output.=($stat==0)? "<div class='pbx-formfield'><label>".PB_FORM_REQTITLE.":</label><input type='text' name='pbx_title' /></div>" : "";
		$sub_form_output.=($stat==0)? "<div class='pbx-formfield'><label>".PB_FORM_REQ.":</label><textarea name='pbx_body'></textarea></div>" : "";
		$sub_form_output.="<div class='pbx-formfield'><label><input type='checkbox' name='pbx_notify' $notify /> ".PB_FORM_NOTIFY."</label></div>";
		$sub_form_output.=($stat==1)? "<div class='pbx-formfield'><label><input type='checkbox' name='pbx_closed' /> ".PB_FORM_EDIT_CLOSE."</label></div>" : "";
		$sub_form_output.="<div class='pbx-formfield'><input type='submit' value='$sub_form_submit' /></div>";
		$sub_form_output.="</form>";
	}

	$sub_form_output.="</div>";

	return $sub_form_output;
}
}
