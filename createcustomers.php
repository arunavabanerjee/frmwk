<?php session_start(); ?>
<?php
include ("../settings.php");
include("../language/$cfg_language");
include ("../classes/db_functions.php");
include ("../classes/security_functions.php");
include ("../classes/form.php");
include ("../classes/display.php");

$lang=new language();
$dbf=new db_functions($cfg_server,$cfg_username,$cfg_password,$cfg_database,$cfg_tableprefix,$cfg_theme,$lang);
$dbf_osc=new db_functions($cfg_osc_server,$cfg_osc_username,$cfg_osc_password,$cfg_osc_database,'',$cfg_theme,$lang);
$sec=new security_functions($dbf,'Admin',$lang);
$display=new display($dbf->conn,$dbf_osc->conn,$cfg_theme,$cfg_currency_symbol,$lang);
$tablename = $cfg_tableprefix.'users';
$auth = $dbf->idToField($tablename,'type',$_SESSION['session_user_id']);
$userLoginName= $dbf->idToField($tablename,'username',$_SESSION['session_user_id']);

if(!$sec->isLoggedIn()){ header ("location: ../login.php"); exit(); }

/**
 * checkemail
 * called via jquery post
 */
$tablecustomer="$cfg_tableprefix".'customers';
if(isset($_POST['emailid'])){
  //check value against db
  $query = "SELECT customers_id, customers_email_address FROM ".$tablecustomer." "; 
  $query .= "WHERE customers_email_address = '".$_POST['emailid']."'"; 
  $result = mysql_fetch_row(mysql_query($query,$dbf->conn)); $html = '';
  if(!empty($result)){ $html .= '<p id="error" style="color:red; font-weight:bold;">Email Already Exists</p>'; }
  echo $html; die();
}

//decides if the form will be used to update or add a user.
if(isset($_GET['action'])) { $action=$_GET['action']; }
else { $action="insert"; } 

//set default values, these will change if $action==update.
$first_name_value=''; $last_name_value=''; $account_number_value=''; $phone_number_value=''; 
$email_value=''; $street_address_value=''; $comments_value=''; $id=-1;

//if action is update, sets variables to what the current users data is.
if($action=="update") {
	if(isset($_GET['id'])){
		$id=$_GET['id'];
		$tablenamecust = "$cfg_tableprefix".'customers';
		$result = mysql_query("SELECT * FROM $tablenamecust WHERE customers_id=\"$id\"",$dbf->conn);
		$row = mysql_fetch_assoc($result);

		//customer variables
		$first_name_value=$row['customers_firstname'];
		$last_name_value=$row['customers_lastname'];
		//$account_number_value=$row['customers_account_number'];
		$phone_number_value=$row['customers_telephone'];
		$email_value=$row['customers_email_address'];
		//$street_address_value=$row['customers_default_address_id'];
		//$comments_value=$row['customers_comments'];	
	}
} 

//var_dump($_SESSION); var_dump($_POST);
?>
<?php include "../top.php" ?>
<body id="page-top">
  <!-- Page Wrapper -->
  <div id="wrapper">
	<!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">
      <!-- Main Content -->
      <div id="content">
      <?php include "../navigation.php" ?>
      <!-- Begin Page Content -->
       <div class="pagebody">
	 <div class="container-fluid">

	<?php if($action=="update") { $display->displayTitle("$lang->updateCustomer"); }
	      else{ $display->displayTitle("$lang->addCustomer"); } ?>
        <?php //creates a form object
	$f1=new form('process_form_customers.php','POST','customers','450',$cfg_theme,$lang);	
	//creates form parts.
	$f1->createInputField_cust("<b>$lang->firstName:</b> ",'text','customers_firstname',"$first_name_value",'24','150');
	$f1->createInputField_cust("<b>$lang->lastName:</b> ",'text','customers_lastname',"$last_name_value",'24','150');
	//$f1->createInputField_cust("<b>$lang->accountNumber:</b> ",'text','customers_account_number',"$account_number_value",'24','150');
	$f1->createInputField_cust("<b>$lang->phoneNumber:</b> ",'text','customers_telephone',"$phone_number_value",'24','150');
	$f1->createInputField_cust("<b>$lang->email:</b> ",'text','customers_email_address',"$email_value",'24','150');
	//$f1->createInputField_cust("<b>$lang->streetAddress:</b> ",'text','customers_default_address_id',"$street_address_value",'24','150');
	//$f1->createInputField_cust("<b>$lang->commentsOrOther:</b> ",'text','customers_comments',"$comments_value",'40','150');
	//sends 2 hidden varibles needed for process_form_users.php.
	echo "<div class='hidden'><input type='hidden' name='action' value='$action'>
		<input type='hidden' name='id' value='$id'></div>";
	$f1->endForm_cust(); ?>
	<?php $dbf->closeDBlink(); ?>

	</div><!-- /.container-fluid -->
	</div>        

   </div><!-- End of Main Content -->

   <script>
    $jqc = jQuery.noConflict(); 
    $jqc('input[type="text"]').keyup(function(){
      if($jqc(this).attr("name") == "customers_email_address"){ 
	var emailval = $jqc(this).val(); console.log(emailval);
	$jqc.post("form_customers.php", {emailid: emailval})
	    .done(function(data){ 
		if(data != ""){
                  if($jqc('#error').length > 0){
		    $jqc('#error').remove();
		    $jqc('form center').append(data);
		  } else {	 
		    $jqc('form center').append(data);
                  } 
		  $jqc('#error').fadeOut(4000);
		}
                if(data == ""){
                  if($jqc('#error').length > 0){
		    $jqc('#error').remove();
		  } 	
		} 
	    });	
      }   
    });
    $jqc('input[type="submit"]').click(function(){
       if( $jqc(document).find('#error').length > 0 ) {
	 $jqc('#error').text('Cannot Submit, Email Already Exists'); 
	 $jqc('#error').css('display','block');
  	 $jqc('#error').fadeOut(4000);
	 return false;
       }  	
    }); 
   </script>	

   <!-- Footer -->
   <?php include "../footer.php" ?>
   <!-- End of Footer -->

   </div> <!-- End of Content Wrapper -->

  </div><!-- End of Page Wrapper -->
<?php include "../bottom.php" ?>
