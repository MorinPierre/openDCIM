<?php
	// Allow the installer to link to the config page
	$devMode=true;
	require_once( "db.inc.php" );
	require_once( "facilities.inc.php" );

	$subheader=__("Data Center Configuration");
	$timestamp=time();
	$salt=md5('unique_salt' . $timestamp);

	if(!$person->SiteAdmin){
		// No soup for you.
		header('Location: '.redirect());
		exit;
	}

	function BuildFileList($returnjson=false){
		$imageselect='<div id="preview"></div><div id="filelist">';

		$filesonly=array();
		$path='./images';
		$dir=scandir($path);
		foreach($dir as $i => $f){
			if(is_file($path.DIRECTORY_SEPARATOR.$f) && round(filesize($path.DIRECTORY_SEPARATOR.$f) / 1024, 2)>=4 && $f!="serverrack.png" && $f!="gradient.png"){
				$imageinfo=getimagesize($path.DIRECTORY_SEPARATOR.$f);
				if(preg_match('/^image/i', $imageinfo['mime'])){
					$imageselect.="<span>$f</span>\n";
					$filesonly[]=$f;
				}
			}
		}
		$imageselect.="</div>";
		if($returnjson){
			header('Content-Type: application/json');
			echo json_encode($filesonly);
		}else{
			return $imageselect;
		}
	}

	// AJAX Requests
	if(isset($_GET['fl'])){
		echo BuildFileList(isset($_GET['json']));
		exit;
	}
	if(isset($_POST['fe'])){ // checking that a file exists
		echo(is_file($_POST['fe']))?1:0;
		exit;
	}
	if(isset($_POST['mt'])){ // Media Types
		$mt=new MediaTypes();
		$mt->MediaType=trim($_POST['mt']);
		$mt->ColorID=$_POST['mtcc'];
		if(isset($_POST['mtid'])){ // If set we're updating an existing entry
			$mt->MediaID=$_POST['mtid'];
			if(isset($_POST['original'])){
				$mt->GetType();
			    header('Content-Type: application/json');
				echo json_encode($mt);
				exit;
			}
			if(isset($_POST['clear']) || isset($_POST['change'])){
				if(isset($_POST['clear'])){
					MediaTypes::ResetType($mt->MediaID);
				}else{
					$newmediaid=$_POST['change'];
					MediaTypes::ResetType($mt->MediaID,$newmediaid);
				}
				if($mt->DeleteType()){
					echo 'u';
				}else{
					echo 'f';
				}
				exit;
			}
			if($mt->UpdateType()){
				echo 'u';
			}else{
				echo 'f';
			}
		}else{
			if($mt->CreateType()){
				echo $mt->MediaID;
			}else{
				echo 'f';
			}
			
		}
		exit;
	}
	if(isset($_POST['mtused'])){
		$count=MediaTypes::TimesUsed($_POST['mtused']);
		if($count==0){
			$mt=new MediaTypes();
			$mt->MediaID=$_POST['mtused'];
			$mt->DeleteType();
		}
		echo $count;
		exit;
	}
	if(isset($_POST['mtlist'])){
		$codeList=MediaTypes::GetMediaTypeList();
		$output='<option value=""></option>';
		foreach($codeList as $mt){
			$output.="<option value=\"$mt->MediaID\">$mt->MediaType</option>";
		}
		echo $output;
		exit;		
	}

	if(isset($_POST['dcal'])){
		$dca = new DeviceCustomAttribute();
		$dca->Label=trim($_POST['dcal']);
		$dca->AttributeType=trim($_POST['dcat']);
		if(isset($_POST['dcar']) && trim($_POST['dcar'])=="true"){
			$dca->Required=1;
		}else{
			$dca->Required=0;
		}
		if(isset($_POST['dcaa']) && trim($_POST['dcaa'])=="true"){
			$dca->AllDevices=1;
		}else{
			$dca->AllDevices=0;
		}
		if($dca->AttributeType == "checkbox") {
			if(trim($_POST['dcav'])=="true") {
				$dca->DefaultValue="1";
			} else {
				$dca->DefaultValue="0";
			}
		} else {
			$dca->DefaultValue=trim($_POST['dcav']);
		}
		if(isset($_POST['dcaid'])){
			$dca->AttributeID=$_POST['dcaid'];
			if(isset($_POST['original'])){
				$dca->GetDeviceCustomAttribute();
				header('Content-Type: application/json');
				echo json_encode($dca);
				exit;
			}
			if(isset($_POST['clear'])){
				if($dca->RemoveDeviceCustomAttribute()){
					echo 'u';
				}else{
					echo 'f';
				}
				exit;
			}
			if(isset($_POST['removeuses'])){
				if($dca->RemoveFromTemplatesAndDevices()){
					echo 'u';
				} else{
					echo 'f';
				}
				exit;
			} 
			if($dca->UpdateDeviceCustomAttribute()){
				echo 'u';
			}else{
				echo 'f';
			}
			exit;
		}else{
			if($dca->CreateDeviceCustomAttribute()){
				echo $dca->AttributeID;
			}else{
				echo 'f';
			}
			exit;
		}

		exit;
	}
	if(isset($_POST['dcaused'])){
		$count=DeviceCustomAttribute::TimesUsed($_POST['dcaused']);
		if($count==0 && isset($_POST['remove'])){
			$dca=new DeviceCustomAttribute();
			$dca->AttributeID=$_POST['dcaused'];
			if($dca->RemoveDeviceCustomAttribute()){
				echo $count;
				exit;
			}else{
				echo "fail";
				exit;
			}
		}
		echo $count;
		exit;
	}
	// END AJAX Requests

	if(isset($_REQUEST["action"]) && $_REQUEST["action"]=="Update"){
		foreach($config->ParameterArray as $key=>$value){
			if($key=="ClassList"){
				$List=explode(", ",$_REQUEST[$key]);
				$config->ParameterArray[$key]=$List;
			}else{
				$config->ParameterArray[$key]=$_REQUEST[$key];
			}
		}
		$config->UpdateConfig();

		//Disable all tooltip items and clear the SortOrder
		$dbh->exec("UPDATE fac_CabinetToolTip SET SortOrder = NULL, Enabled=0;");
		if(isset($_POST["tooltip"]) && !empty($_POST["tooltip"])){
			$p=$dbh->prepare("UPDATE fac_CabinetToolTip SET SortOrder=:sortorder, Enabled=1 WHERE Field=:field LIMIT 1;");
			foreach($_POST["tooltip"] as $order => $field){
				$p->bindParam(":sortorder",$order);
				$p->bindParam(":field",$field);
				$p->execute();
			}
		}

		//Disable all cdu tooltip items and clear the SortOrder
		$dbh->exec("UPDATE fac_CDUToolTip SET SortOrder = NULL, Enabled=0;");
		if(isset($_POST["cdutooltip"]) && !empty($_POST["cdutooltip"])){
			$p=$dbh->prepare("UPDATE fac_CDUToolTip SET SortOrder=:sortorder, Enabled=1 WHERE Field=:field LIMIT 1;");
			foreach($_POST["cdutooltip"] as $order => $field){
				$p->bindParam(":sortorder",$order);
				$p->bindParam(":field",$field);
				$p->execute();
			}
		}
		exit;
	}

	// make list of department types
	$i=0;
	$classlist="";
	foreach($config->ParameterArray["ClassList"] as $item){
		$classlist .= $item;
		if($i+1 != count($config->ParameterArray["ClassList"])){
			$classlist.=", ";
		}
		$i++;
	}

	$imageselect=BuildFileList();

	function formatOffset($offset) {
			$hours = $offset / 3600;
			$remainder = $offset % 3600;
			$sign = $hours > 0 ? '+' : '-';
			$hour = (int) abs($hours);
			$minutes = (int) abs($remainder / 60);

			if ($hour == 0 AND $minutes == 0) {
				$sign = ' ';
			}
			return 'GMT' . $sign . str_pad($hour, 2, '0', STR_PAD_LEFT) 
					.':'. str_pad($minutes,2, '0');

	}

	$regions=array();
	foreach(DateTimeZone::listIdentifiers() as $line){
		$pieces=explode("/",$line);
		if(isset($pieces[1])){
			$regions[$pieces[0]][]=$line;
		}
	}

	$tzmenu='<ul id="tzmenu">';
	foreach($regions as $country => $cityarray){
		$tzmenu.="\t<li>$country\n\t\t<ul>";
		foreach($cityarray as $key => $city){
			$z=new DateTimeZone($city);
			$c=new DateTime(null, $z);
			$adjustedtime=$c->format('H:i a');
			$offset=formatOffset($z->getOffset($c));
			$tzmenu.="\t\t\t<li><a href=\"#\" data=\"$city\">$adjustedtime - $offset $city</a></li>\n";
		}
		$tzmenu.="\t\t</ul>\t</li>";
	}
	$tzmenu.='</ul>';

	// Build list of cable color codes
	$cablecolors="";
	$colorselector='<select name="mediacolorcode[]"><option value="0"></option>';

	$codeList=ColorCoding::GetCodeList();
	if(count($codeList)>0){
		foreach($codeList as $cc){
			$colorselector.='<option value="'.$cc->ColorID.'">'.$cc->Name.'</option>';
			$cablecolors.='<div>
					<div><img src="images/del.gif"></div>
					<div><input type="text" name="colorcode[]" data='.$cc->ColorID.' value="'.$cc->Name.'"></div>
					<div><input type="text" name="ccdefaulttext[]" value="'.$cc->DefaultNote.'"></div>
				</div>';
		}
	}
	$colorselector.='</select>';

	// Build list of media types
	$mediatypes="";
	$mediaList=MediaTypes::GetMediaTypeList();

	if(count($mediaList)>0){
		foreach($mediaList as $mt){
			$mediatypes.='<div>
					<div><img src="images/del.gif"></div>
					<div><input type="text" name="mediatype[]" data='.$mt->MediaID.' value="'.$mt->MediaType.'"></div>
					<div><select name="mediacolorcode[]"><option value=""></option>';
			foreach($codeList as $cc){
				$selected=($mt->ColorID==$cc->ColorID)?' selected':'';
				$mediatypes.="<option value=\"$cc->ColorID\"$selected>$cc->Name</option>";
			}
			$mediatypes.='</select></div>
				</div>';
		}
	}

	// build list of existing device custom attributes
	$customattrs="";
	$dcaTypeList=DeviceCustomAttribute::GetDeviceCustomAttributeTypeList();
	$dcaList=DeviceCustomAttribute::GetDeviceCustomAttributeList();
	if(count($dcaList)>0) {
		foreach($dcaList as $dca) {
			$customattrs.='<div>
					<div><img src="images/del.gif"></div>
					<div><input type="text" name="dcalabel[]" data='.$dca->AttributeID.' value="'.$dca->Label.'"></div>
					<div><select name="dcatype[]" id="dcatype">';
			foreach($dcaTypeList as $dcatype){
				$selected=($dca->AttributeType==$dcatype)?' selected':'';
				$customattrs.="<option value=\"$dcatype\"$selected>$dcatype</option>";
			}
			$customattrs.='</select></div>
					<div><input type="checkbox" name="dcarequired[]"';
			if($dca->Required) { $customattrs.=' checked'; }
			$customattrs.='></div>
					<div><input type="checkbox" name="dcaalldevices[]"';
			if($dca->AllDevices) { $customattrs.=' checked'; }
			$currinputtype="text";
			$currchecked="";
			if($dca->AttributeType=="checkbox") { 
				$currinputtype="checkbox"; 
				if($dca->DefaultValue) {
					$currchecked=" checked";
				}
			}
			$customattrs.='></div>
					<div><input type="'.$currinputtype.'" name="dcavalue[]" value="'.$dca->DefaultValue.'" '.$currchecked.'></div>
					</div>';
		}
	}

        $dcaTypeSelector='<select name="dcatype[]" id="dcatype">';
        if(count($dcaTypeList)>0){
                foreach($dcaTypeList as $dcatype){
			$selected=($dcatype=='string')?' selected':'';
                        $dcaTypeSelector.="<option value=\"$dcatype\"$selected>$dcatype</option>";
                }
        }
        $dcaTypeSelector.="</select>";


	// Figure out what the URL to this page
	$href="";
	$href.=(array_key_exists('HTTPS', $_SERVER)) ? 'https://' : 'http://';
	$href.=$_SERVER['SERVER_NAME'];
	$href.=substr($_SERVER['REQUEST_URI'], 0, -strlen(basename($_SERVER['REQUEST_URI'])));

	// Build up the list of items available for the tooltips
	$tooltip="<select id=\"tooltip\" name=\"tooltip[]\" multiple=\"multiple\">\n";
	$sql="SELECT * FROM fac_CabinetToolTip ORDER BY SortOrder ASC, Enabled DESC, Label ASC;";
	foreach($dbh->query($sql) as $row){
		$selected=($row["Enabled"])?" selected":"";
		$tooltip.="<option value=\"".$row['Field']."\"$selected>".__($row["Label"])."</option>\n";
	}
	$tooltip.="</select>";

	// Build up the list of items available for the tooltips
	$cdutooltip="<select id=\"cdutooltip\" name=\"cdutooltip[]\" multiple=\"multiple\">\n";
	$sql="SELECT * FROM fac_CDUToolTip ORDER BY SortOrder ASC, Enabled DESC, Label ASC;";
	foreach($dbh->query($sql) as $row){
		$selected=($row["Enabled"])?" selected":"";
		$cdutooltip.="<option value=\"".$row['Field']."\"$selected>".__($row["Label"])."</option>\n";
	}
	$cdutooltip.="</select>";
?>
<!doctype html>
<html>
<head>
  <meta http-equiv="X-UA-Compatible" content="IE=Edge">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  
  <title>openDCIM Data Center Inventory</title>
  <link rel="stylesheet" href="css/inventory.php" type="text/css">
  <link rel="stylesheet" href="css/jquery.miniColors.css" type="text/css">
  <link rel="stylesheet" href="css/jquery-ui.css" type="text/css">
  <link rel="stylesheet" href="css/jquery.ui.multiselect.css" type="text/css">
  <link rel="stylesheet" href="css/uploadifive.css" type="text/css">
  <!--[if lt IE 9]>
  <link rel="stylesheet"  href="css/ie.css" type="text/css">
  <![endif]-->
  
  <script type="text/javascript" src="scripts/jquery.min.js"></script>
  <script type="text/javascript" src="scripts/jquery-ui.min.js"></script>
  <script type="text/javascript" src="scripts/jquery.uploadifive.js"></script>
  <script type="text/javascript" src="scripts/jquery.miniColors.js"></script>
  <script type="text/javascript" src="scripts/jquery.ui.multiselect.js"></script>
  <script type="text/javascript">
	$(document).ready(function(){
		// ToolTips
		$('#tooltip, #cdutooltip').multiselect();
		$("select:not('#tooltip, #cdutooltip')").each(function(){
			if($(this).attr('data')){
				$(this).val($(this).attr('data'));
			}
		});

		// Applies to everything

		$("#configtabs").tabs({
			activate: function( event, ui ) {
				if(ui.newPanel.selector=="#preflight"){
					var preflight=document.getElementsByTagName("iframe");
					preflight[0].style.width='100%';
					preflight[0].style.height=preflight[0].contentWindow.document.body.offsetHeight + 50 + "px";
				}
			}
		});
		$('#configtabs input[defaultvalue],#configtabs select[defaultvalue]').each(function(){
			$(this).parent().after('<div><button type="button">&lt;--</button></div><div><span>'+$(this).attr('defaultvalue')+'</span></div>');
		});
		$("#configtabs input").each(function(){
			$(this).attr('id', $(this).attr('name'));
			$(this).removeAttr('defaultvalue');
		});
		$("#configtabs button").each(function(){
			var a = $(this).parent().prev().find('input,select');
			$(this).click(function(){
				a.val($(this).parent().next().children('span').text());
				if(a.hasClass('color-picker')){
					a.minicolors('value', $(this).parent().next().children('span').text()).trigger('change');
				}
				a.triggerHandler("paste");
				a.focus();
				$('input[name="OrgName"]').focus();
			});
		});

		// Style - Site

		function colorchange(hex,id){
			if(id==='HeaderColor'){
				$('#header').css('background-color',hex);
			}else if(id==='BodyColor'){
				$('.main').css('background-color',hex);
			}
		}
		$(".color-picker").minicolors({
			letterCase: 'uppercase',
			change: function(hex, rgb){
					colorchange($(this).val(),$(this).attr('id'));
			}
		}).change(function(){colorchange($(this).val(),$(this).attr('id'));});
		$('input[name="LinkColor"]').blur(function(){
			$("head").append("<style type=\"text/css\">a:link, a:hover, a:visited:hover {color: "+$(this).val()+";}</style>");
		});
		$('input[name="VisitedLinkColor"]').blur(function(){
			$("head").append("<style type=\"text/css\">a:visited {color: "+$(this).val()+";}</style>");
		});

		// Reporting

		$('#PDFLogoFile').click(function(){
			var input=this;
			var originalvalue=this.value;
			$.get('',{fl: '1'}).done(function(data){
				$("#imageselection").html(data);
				var upload=$('<input>').prop({type: 'file', name: 'dev_file_upload', id: 'dev_file_upload'}).data('dir','images');
				$("#imageselection").dialog({
					resizable: false,
					height:500,
					width: 670,
					modal: true,
					buttons: {
	<?php echo '					',__("Select"),': function() {'; ?>
							if($('#imageselection #preview').attr('image')!=""){
								$('#PDFLogoFile').val($('#imageselection #preview').attr('image'));
							}
							$(this).dialog("close");
						}
					},
					close: function(){
							// they clicked the x, set the value back if something was uploaded
							input.value=originalvalue;
							$('#header').css('background-image', 'url("images/'+input.value+'")');
							$(this).dialog("destroy");
						}
				}).data('input',input);;
				$("#imageselection").next('div').prepend(upload);
				uploadifive();
				$("#imageselection span").each(function(){
					var preview=$('#imageselection #preview');
					$(this).click(function(){
						preview.html('<img src="images/'+$(this).text()+'" alt="preview">').attr('image',$(this).text()).css('border-width', '5px');
						preview.children('img').load(function(){
							var topmargin=0;
							var leftmargin=0;
							if($(this).height()<$(this).width()){
								$(this).width(preview.innerHeight());
								$(this).css({'max-width': preview.innerWidth()+'px'});
								topmargin=Math.floor((preview.innerHeight()-$(this).height())/2);
							}else{
								$(this).height(preview.innerHeight());
								$(this).css({'max-height': preview.innerWidth()+'px'});
								leftmargin=Math.floor((preview.innerWidth()-$(this).width())/2);
							}
							$(this).css({'margin-top': topmargin+'px', 'margin-left': leftmargin+'px'});
						});
						$("#imageselection span").each(function(){
							$(this).removeAttr('style');
						});
						$(this).css('border','1px dotted black')
						$('#header').css('background-image', 'url("images/'+$(this).text()+'")');
					});
					if($('#PDFLogoFile').val()==$(this).text()){
						$(this).click();
					}
				});
			});
		});

		// Make SNMP community visible
		$('#SNMPCommunity,#v3AuthPassphrase,#v3PrivPassphrase')
			.focus(function(){$(this).attr('type','text');})
			.blur(function(){$(this).attr('type','password');});

		// General - Time and Measurements

		$("#tzmenu").menu();
		$("#tzmenu ul > li").click(function(e){
			e.preventDefault();
			$("#timezone").val($(this).children('a').attr('data'));
			$("#tzmenu").toggle();
		});
		$("#tzmenu").focusout(function(){
			$("#tzmenu").toggle();
		});
		$('<button type="button">').attr({
				id: 'btn_tzmenu'
		}).appendTo("#general");
		$('#btn_tzmenu').each(function(){
			var input=$("#timezone");
			var offset=input.position();
			var height=input.outerHeight();
			$(this).css({
				'height': height+'px',
				'width': height+'px',
				'position': 'absolute',
				'left': offset.left+input.width()-height-((input.outerHeight()-input.height())/2)+'px',
				'top': offset.top+'px'
			}).click(function(){
				$("#tzmenu").toggle();
				$("#tzmenu").focus().click();
			});
			offset=$(this).position();
			$("#tzmenu").css({
				'position': 'absolute',
				'left': offset.left+(($(this).outerWidth()-$(this).width())/2)+'px',
				'top': offset.top+height+'px'
			});
			$(this).addClass('text-arrow');
		});

		// Cabling - Media Types
		function removemedia(row){
			$.post('',{mtused: row.find('div:nth-child(2) input').attr('data')}).done(function(data){
				if(data.trim()==0){
					row.effect('explode', {}, 500, function(){
						$(this).remove();
					});
				}else{
					var defaultbutton={
						"<?php echo __("Clear all"); ?>": function(){
							$.post('',{mtid: row.find('div:nth-child(2) input').attr('data'),mt: '', mtcc: '', clear: ''}).done(function(data){
								if(data.trim()=='u'){ // success
									$('#modal').dialog("destroy");
									row.effect('explode', {}, 500, function(){
										$(this).remove();
									});
								}else{ // failed to delete
									$('#modaltext').html('AAAAAAAAAAHHHHHHHHHH!!!  *crash* *fire* *chaos*<br><br><?php echo __("Something just went horribly wrong."); ?>');
									$('#modal').dialog('option','buttons',cancelbutton);
								}
							});
						}
					}
					var replacebutton={
						"<?php echo __("Replace"); ?>": function(){
							// send command to replace all connections with x
							$.post('',{mtid: row.find('div:nth-child(2) input').attr('data'),mt: '', mtcc: '', change: $('#modal select').val()}).done(function(data){
								if(data.trim()=='u'){ // success
									$('#modal').dialog("destroy");
									row.effect('explode', {}, 500, function(){
										$(this).remove();
									});
								}else{ // failed to delete
									$('#modaltext').html('AAAAAAAAAAHHHHHHHHHH!!!  *crash* *fire* *chaos*<br><br><?php echo __("Something just went horribly wrong."); ?>');
									$('#modal').dialog('option','buttons',cancelbutton);
								}
							});
						}
					}
					var cancelbutton={
						"<?php echo __("Cancel"); ?>": function(){
							$(this).dialog("destroy");
						}
					}
<?php echo "					var modal=$('<div />', {id: 'modal', title: '".__("Media Type Delete Override")."'}).html('<div id=\"modaltext\">".__("This media type is in use somewhere. Select an alternate type to assign to all the records to or choose clear all.")."<select id=\"replaceme\"></select></div>').dialog({"; ?>
						dialogClass: 'no-close',
						appendTo: 'body',
						modal: true,
						buttons: $.extend({}, defaultbutton, cancelbutton)
					});
					$.post('',{mtlist: ''}).done(function(data){
						var choices=$('<select />');
						choices.html(data);
						choices.find('option').each(function(){
							if($(this).val()==row.find('div:nth-child(2) input').attr('data')){$(this).remove();}
						});
						choices.change(function(){
							if($(this).val()==''){ // clear all
								modal.dialog('option','buttons',$.extend({}, defaultbutton, cancelbutton));
							}else{ // replace
								modal.dialog('option','buttons',$.extend({}, replacebutton, cancelbutton));
							}
						});
						modal.find($('#replaceme')).replaceWith(choices);
						
					});
				}
			});

		}

		var blankmediarow=$('<div />').html('<div><img src="images/del.gif"></div><div><input id="mediatype[]" name="mediatype[]" type="text"></div><div><select name="mediacolorcode[]"></select></div>');
		function bindmediarow(row){
			var addrem=row.find('div:first-child');
			var mt=row.find('div:nth-child(2) input');
			var mtcc=row.find('div:nth-child(3) select');
			if(mt.val().trim()!='' && addrem.attr('id')!='newline'){
				addrem.click(function(){
					removemedia(row);
				});
			}
			mt.keypress(function(event){
				if(event.keyCode==10 || event.keyCode==13){
					event.preventDefault();
					mt.change();
				}
			});
			function update(inputobj){
				if(mt.val().trim()==''){
					// reset value to previous
					$.post('',{mt: mt.val(), mtid: mt.attr('data'), mtcc: mtcc.val(),original:''}).done(function(jsondata){
						mt.val(jsondata.MediaType);
						mtcc.val(jsondata.ColorID);
					});
					mt.effect('highlight', {color: 'salmon'}, 1500);
					mtcc.effect('highlight', {color: 'salmon'}, 1500);
				}else{
					// attempt to update
					$.post('',{mt: mt.val(), mtid: mt.attr('data'), mtcc: mtcc.val()}).done(function(data){
						if(data.trim()=='f'){ // fail
							$.post('',{mt: mt.val(), mtid: mt.attr('data'), mtcc: mtcc.val(),original:''}).done(function(jsondata){
								mt.val(jsondata.MediaType);
								mtcc.val(jsondata.ColorID);
							});
							mt.effect('highlight', {color: 'salmon'}, 1500);
							mtcc.effect('highlight', {color: 'salmon'}, 1500);
						}else if(data.trim()=='u'){ // updated
							mt.effect('highlight', {color: 'lightgreen'}, 2500);
							mtcc.effect('highlight', {color: 'lightgreen'}, 2500);
						}else{ // created
							var newitem=blankmediarow.clone();
							newitem.find('div:nth-child(2) input').val(mt.val()).attr('data',data.trim());
							newitem.find('div:nth-child(3) select').replaceWith(mtcc.clone());
							bindmediarow(newitem);
							row.before(newitem);
							newitem.find('div:nth-child(3) select').val(mtcc.val()).focus();
							if(addrem.attr('id')=='newline'){
								mt.val('');
								mtcc.val('');
							}else{
								row.remove();
							}
						}
					});
				}
			}
			mt.change(function(){
				update($(this));
			});
			mtcc.change(function(){
				var row=$(this).parent('div').parent('div');
				if(row.find('div:first-child').attr('id')!='newline'){
					update($(this));
				}else if(row.find('div:nth-child(2) input').val().trim()!=''){
					update($(this));
				}
			});
		}

		// Add a new blank row
		$('#mediatypes > div ~ div > div:first-child').each(function(){
			if($(this).attr('id')=='newline'){
				var row=$(this).parent('div');
				$(this).click(function(){
					var newitem=blankmediarow.clone();
					// Clone the current dropdown list
					newitem.find('select[name="mediacolorcode[]"]').replaceWith((row.find('select[name="mediacolorcode[]"]').clone()));
					newitem.find('div:first-child').click(function(){
						removecolor($(this).parent('div'),false);
					});
					bindmediarow(newitem);
					row.before(newitem);
				});
			}
			bindmediarow($(this).parent('div'));
		});

		// Update color drop lists
		function updatechoices(){
			$.get('api/v1/colorcode').done(function(data){
				if(!data.error){
					$('#mediatypes > div ~ div').each(function(){
						var list=$(this).find('select[name="mediacolorcode[]"]');
						var dc=list.val();
						list.html($('<option>').val('0'));
						for(var i in data.colorcode){
							list.append($('<option>').val(data.colorcode[i].ColorID).text(data.colorcode[i].Name));
						}
						list.val(dc);
					});
				}
			});
		}

		// Cabling - Cable Colors

		function removecolor(rowobject,lookup){
			if(!lookup){
				rowobject.remove();
			}else{
				$.get('api/v1/colorcode/'+rowobject.find('div:nth-child(2) input').attr('data')+'/timesused').done(function(data){
					if(data.colorcode==0){
						$.ajax('api/v1/colorcode/'+rowobject.find('div:nth-child(2) input').attr('data'),{type: 'delete'}).done(function(data){
							if(!data.error){
								updatechoices();
								rowobject.effect('explode', {}, 500, function(){
									$(this).remove();
								});
							}
						});
					}else{
						var defaultbutton={
							"<?php echo __("Clear all"); ?>": function(){
								$.post('api/v1/colorcode/'+rowobject.find('div:nth-child(2) input').attr('data')+'/replacewith/'+$('#modal select').val()).done(function(data){
									if(!data.error){ // success
										$.ajax('api/v1/colorcode/'+rowobject.find('div:nth-child(2) input').attr('data'),{type: 'delete'});
										$('#modal').dialog("destroy");
										updatechoices();
										rowobject.effect('explode', {}, 500, function(){
											$(this).remove();
										});
									}else{ // failed to delete
										$('#modaltext').html('AAAAAAAAAAHHHHHHHHHH!!!  *crash* *fire* *chaos*<br><br><?php echo __("Something just went horribly wrong."); ?>');
										$('#modal').dialog('option','buttons',cancelbutton);
									}
								});
							}
						}
						var replacebutton={
							"<?php echo __("Replace"); ?>": function(){
								// send command to replace all connections with x
								$.post('api/v1/colorcode/'+rowobject.find('div:nth-child(2) input').attr('data')+'/replacewith/'+$('#modal select').val()).done(function(data){
									if(!data.error){ // success
										$.ajax('api/v1/colorcode/'+rowobject.find('div:nth-child(2) input').attr('data'),{type: 'delete'});
										$('#modal').dialog("destroy");
										updatechoices();
										rowobject.effect('explode', {}, 500, function(){
											$(this).remove();
										});
										// Need to trigger a reload of any of the media types that had this 
										// color so they will display the new color
										$('#mediatypes > div ~ div:not(:last-child) input').val('').change();
									}else{ // failed to delete
										$('#modaltext').html('AAAAAAAAAAHHHHHHHHHH!!!  *crash* *fire* *chaos*<br><br><?php echo __("Something just went horribly wrong."); ?>');
										$('#modal').dialog('option','buttons',cancelbutton);
									}
								});
							}
						}
						var cancelbutton={
							"<?php echo __("Cancel"); ?>": function(){
								$(this).dialog("destroy");
							}
						}
<?php echo "						var modal=$('<div />', {id: 'modal', title: '".__("Code Delete Override")."'}).html('<div id=\"modaltext\">".__("This code is in use somewhere. You can either choose to clear all instances of this color being used or choose to have them replaced with another color.")." <select id=\"replaceme\"></select></div>').dialog({"; ?>
							dialogClass: 'no-close',
							appendTo: 'body',
							modal: true,
							buttons: $.extend({}, defaultbutton, cancelbutton)
						});
						var choices=$('div#mediatypes.table div:last-child div select').clone();
						choices.find('option').each(function(){
							if($(this).val()==rowobject.find('div:nth-child(2) input').attr('data')){$(this).remove();}
						});
						choices.change(function(){
							if($(this).val()==''){ // clear all
								modal.dialog('option','buttons',$.extend({}, defaultbutton, cancelbutton));
							}else{ // replace
								modal.dialog('option','buttons',$.extend({}, replacebutton, cancelbutton));
							}
						});
						modal.find($('#replaceme')).replaceWith(choices);
					}
				});
			}
		}
		var blankrow=$('<div />').html('<div><img src="images/del.gif"></div><div><input type="text" name="colorcode[]"></div><div><input type="text" name="ccdefaulttext[]"></div>');
		function bindrow(row){
			var addrem=row.find('div:first-child');
			var cc=row.find('div:nth-child(2) input');
			var ccdn=row.find('div:nth-child(3) input');
			if(cc.val().trim()!='' && addrem.attr('id')!='newline'){
				addrem.click(function(){
					removecolor(row,true);
				});
			}
			cc.keypress(function(event){
				if(event.keyCode==10 || event.keyCode==13){
					event.preventDefault();
					cc.change();
				}
			});
			ccdn.keypress(function(event){
				if(event.keyCode==10 || event.keyCode==13){
					event.preventDefault();
					ccdn.change();
				}
			});
			function FlashGreen(){
				cc.effect('highlight', {color: 'lightgreen'}, 2500);
				ccdn.effect('highlight', {color: 'lightgreen'}, 2500);
			}
			function FlashRed(){
				cc.effect('highlight', {color: 'salmon'}, 1500);
				ccdn.effect('highlight', {color: 'salmon'}, 1500);
			}
			row.find('div > input').each(function(){
				// If a value changes then check it for conflicts, if no conflict update
				$(this).change(function(){
					if(cc.val().trim()!=''){
						// if this is defined we're doing an update operation
						if(cc.attr('data')){
							$.post('api/v1/colorcode/'+cc.attr('data'),{ColorID: cc.attr('data'),Name: cc.val(),DefaultNote: ccdn.val()}).done(function(data){
								if(data.error){
									$.get('api/v1/colorcode/'+cc.attr('data')).done(function(data){
										for(var i in data.colorcode){
											var colorcode=data.colorcode[i];
											cc.val(colorcode.Name);
											ccdn.val(colorcode.DefaultNote);
										}
									});
									FlashRed();
								}else{ // updated
									FlashGreen();
									// update media type color pick lists
									updatechoices();
								}
							});
						}else{ // Color code not defined we must be creating a new one
							$.ajax('api/v1/colorcode/'+cc.val(),{type: 'put',data:{Name: cc.val(),DefaultNote: ccdn.val()}}).done(function(data){
								if(data.error){
									FlashRed();
								}else{
									var newitem=blankrow.clone();
									for(var i in data.colorcode){
										newitem.find('div:nth-child(2) input').val(cc.val()).attr('data',data.colorcode[i].ColorID);
									}
									bindrow(newitem);
									row.before(newitem);
									newitem.find('div:nth-child(3) input').val(ccdn.val()).focus();
									if(addrem.attr('id')=='newline'){
										cc.val('');
										ccdn.val('');
									}else{
										row.remove();
									}
									// update media type color pick lists
									updatechoices();
								}
							});
						}
					}else if(cc.val().trim()=='' && ccdn.val().trim()=='' && addrem.attr('id')!='newline'){
						// If both blanks are emptied of values and they were an existing data pair
						$.get('api/v1/colorcode/'+cc.attr('data')).done(function(data){
							for(var i in data.colorcode){
								var colorcode=data.colorcode[i];
								cc.val(colorcode.Name);
								ccdn.val(colorcode.DefaultNote);
							}
						});
						FlashRed();
					}
				});
			});
		}
		$('#cablecolor > div ~ div > div:first-child').each(function(){
			if($(this).attr('id')=='newline'){
				var row=$(this).parent('div');
				$(this).click(function(){
					var newitem=blankrow.clone();
					newitem.find('div:first-child').click(function(){
						removecolor($(this).parent('div'),false);
					});
					bindrow(newitem);
					row.before(newitem);
				});
			}
			bindrow($(this).parent('div'));
		});

		// device custom attribute rows
		var blankdcarow=$('<div />').html('<div><img src="images/del.gif"></div><div><input type="text" name="dcalabel[]"></div><div><select name="dcatype[]" id="dcatype"></select></div></div><div><input type="checkbox" name="dcarequired[]"></div><div><input type="checkbox" name=dcaalldevices[]"></div><div><input type="text" name="dcavalue[]"></div>');
		function binddcarow(row) {
			var addrem=row.find('div:first-child');
			var dcal=row.find('div:nth-child(2) input');
			var dcat=row.find('div:nth-child(3) select');
			var dcar=row.find('div:nth-child(4) input');
			var dcaa=row.find('div:nth-child(5) input');
			var dcav=row.find('div:nth-child(6) input');
			if(dcal.val().trim()!='' && addrem.attr('id')!='newline'){
				addrem.click(function(){
					removedca(row,true);
				});
			}
			dcal.keypress(function(event){
				if(event.keyCode==10 || event.keyCode==13){
					event.preventDefault();
					dcal.change();
				}
			});
			dcar.keypress(function(event){
				if(event.keyCode==10 || event.keyCode==13){
					event.preventDefault();
					dcar.change();
				}
			});
			dcaa.keypress(function(event){
				if(event.keyCode==10 || event.keyCode==13){
					event.preventDefault();
					dcaa.change();
				}
			});
			dcav.keypress(function(event){
				if(event.keyCode==10 || event.keyCode==13){
					event.preventDefault();
					dcav.change();
				}
			});
			if(dcat.length>0){dcat.data('current', dcat.val());}
			function update(inputobj){
				var dcavtosend=dcav.val();
				if(dcat.val()=='checkbox'){
					dcavtosend=dcav.prop('checked');
				}	
				if(dcal.val().trim()==''){
					//reset to previous value
					$.post('',{dcal: dcal.val(), dcaid: dcal.attr('data'), dcat: dcat.val(),dcar: dcar.prop('checked'),dcaa: dcaa.prop('checked'),dcav: dcavtosend,original:''}).done(function(jsondata){
						dcal.val(jsondata.Label);
						dcat.val(jsondata.AttributeType);
						dcar.val(jsondata.Required);
						dcaa.val(jsondata.AllDevices);
						dcav.val(jsondata.DefaultValue);
					});
					dcal.effect('highlight', {color: 'salmon'}, 1500);
					dcat.effect('highlight', {color: 'salmon'}, 1500);
					dcar.effect('highlight', {color: 'salmon'}, 1500);
					dcaa.effect('highlight', {color: 'salmon'}, 1500);
					dcav.effect('highlight', {color: 'salmon'}, 1500);
				} else {
					// attempt to update
					$.post('',{dcal: dcal.val(), dcaid: dcal.attr('data'), dcat: dcat.val(), dcar: dcar.prop('checked'), dcaa: dcaa.prop('checked'), dcav: dcavtosend}).done(function(data){
						if(data.trim()=='f'){ //fail
							$.post('',{dcal: dcal.val(), dcaid:dcal.attr('data'), dcat: dcat.val(), dcar: dcar.prop('checked'), dcaa: dcaa.prop('checked'), dcav: dcavtosend,original:''}).done(function(jsondata){
							dcal.val(jsondata.Label);
							dcat.val(jsondata.AttributeType);
							dcar.val(jsondata.Required);
							dcaa.val(jsondata.AllDevices);
							dcav.val(jsondata.DefaultValue);
							});
							dcal.effect('highlight', {color: 'salmon'}, 1500);
							dcat.effect('highlight', {color: 'salmon'}, 1500);
							dcar.effect('highlight', {color: 'salmon'}, 1500);
							dcaa.effect('highlight', {color: 'salmon'}, 1500);
							dcav.effect('highlight', {color: 'salmon'}, 1500);
						} else if(data.trim()=='u') { // updated
							dcal.effect('highlight', {color: 'lightgreen'}, 2500);
							dcat.effect('highlight', {color: 'lightgreen'}, 2500);
							dcar.effect('highlight', {color: 'lightgreen'}, 2500);
							dcaa.effect('highlight', {color: 'lightgreen'}, 2500);
							dcav.effect('highlight', {color: 'lightgreen'}, 2500);
						} else { // created
							var newitem=blankdcarow.clone();
							newitem.find('div:nth-child(2) input').val(dcal.val()).attr('data',data.trim());
							newitem.find('div:nth-child(3) select').replaceWith(dcat.clone());
							newitem.find('div:nth-child(3) select').val(dcat.val());
							newitem.find('div:nth-child(4) input').replaceWith(dcar.clone());
							newitem.find('div:nth-child(5) input').replaceWith(dcaa.clone());
							if(newitem.find('div:nth-child(3) select').val() == "checkbox") {
								newitem.find('div:nth-child(6) input').attr('type', 'checkbox');
								newitem.find('div:nth-child(6) input').prop('checked', dcav.prop('checked'));
							}
							newitem.find('div:nth-child(6) input').val(dcav.val());
							binddcarow(newitem);
							row.before(newitem);
							newitem.find('div:nth-child(6) input').val(dcav.val()).focus();
							if(addrem.attr('id')=='newline'){
								dcal.val('');
								dcat.val('string');
								dcar.prop('checked',false);
								dcaa.prop('checked',false);
								dcav.attr('type', 'text');
								dcav.val('');
							} else {
								row.remove();
							}	
						}
					});
				}
			}
			dcal.change(function(){
				update($(this));
			});
			dcat.change(function(){
				var row=$(this).parent('div').parent('div');
				var currtype=$(this);
				function processChange() { 
					if(currtype.val() == "checkbox") {
						row.find('div:nth-child(6) input').attr('type', 'checkbox');
						row.find('div:nth-child(6) input').prop('checked', false);
						row.find('div:nth-child(6) input').val('');
						
					} else {
						row.find('div:nth-child(6) input').attr('type', 'text');
						row.find('div:nth-child(6) input').val('');
					}
					if(row.find('div:first-child').attr('id')!='newline'){
						update(currtype);
					} else if(row.find('div:nth-child(2) input').val().trim()!=''){
						update(currtype);
					}
					currtype.data('current', currtype.val());
				}
				
				if(row.find('div:first-child').attr('id')=='newline') { 
					processChange();
				} else {
					$.post('',{dcaused: row.find('div:nth-child(2) input').attr('data')}).done(function(data){
						if(data.trim()==0){
							// if not in use, just let the type change
							processChange();
						} else if(data.trim()=="fail") {
							var cancelbutton={
								"<?php echo __("Cancel"); ?>": function(){
									currtype.val(currtype.data('current'));
									$(this).dialog("destroy");
								}
							}
							<?php echo "				var modal=$('<div />', {id: 'modal', title: '".__("Custom Device Attribute Type Change Error")."'}).html('<div id=\"modaltext\">AAAAAAAAAAHHHHHHHHHH!!!  *crash* *fire* *chaos*<br><br>".__("Something just went horribly wrong.")."</div>').dialog({"; ?>
							dialogClass: 'no-close',
							appendTo: 'body',
							modal: true,
							buttons: $.extend({}, cancelbutton)
							});
						} else {
							var defaultbutton={
								"<?php echo __("Change Type and Clear all uses"); ?>": function(){
									$.post('',{dcaid: row.find('div:nth-child(2) input').attr('data'),dcal: '', dcar: '', dcaa: '', dcav: '', dcat: '', removeuses: ''}).done(function(data){
										if(data.trim()=='u'){ // success
											$('#modal').dialog("destroy");
											processChange();
										}else{ // failed to delete
											$('#modaltext').html('AAAAAAAAAAHHHHHHHHHH!!!  *crash* *fire* *chaos*<br><br><?php echo __("Something just went horribly wrong."); ?>');
											$('#modal').dialog('option','buttons',cancelbutton);
											currtype.val(currtype.data('current'));
										}
									});
								}
							}
							var cancelbutton={
								"<?php echo __("Cancel"); ?>": function(){
									currtype.val(currtype.data('current'));
									$(this).dialog("destroy");
								}
							}
							<?php echo "				var modal=$('<div />', {id: 'modal', title: '".__("Custom Device Attribute Type Change Override")."'}).html('<div id=\"modaltext\">".__("This custom device attribute is in use somewhere. If you choose to change the attribute type, it will be cleared from all devices and device templates.")."</div>').dialog({"; ?>
							dialogClass: 'no-close',
							appendTo: 'body',
							modal: true,
							buttons: $.extend({}, defaultbutton, cancelbutton)
							});
						}
				});
			}
		});
			// TODO it seems like these 3 could be condensed, but when i made it a single function everything freaked out
			dcar.change(function(){
				var row=$(this).parent('div').parent('div');
				if(row.find('div:first-child').attr('id')!='newline'){
					update($(this));
				} else if(row.find('div:nth-child(2) input').val().trim()!=''){
					update($(this));
				}
			});
			dcaa.change(function(){
				var row=$(this).parent('div').parent('div');
				if(row.find('div:first-child').attr('id')!='newline'){
					update($(this));
				} else if(row.find('div:nth-child(2) input').val().trim()!=''){
					update($(this));
				}
			});
			dcav.change(function(){
				var row=$(this).parent('div').parent('div');
				if(row.find('div:first-child').attr('id')!='newline'){
					update($(this));
				} else if(row.find('div:nth-child(2) input').val().trim()!=''){
					update($(this));
				}
			});
		}
		$('#customattrs > div ~ div > div:first-child').each(function(){
			if($(this).attr('id')=='newline'){
				var row=$(this).parent('div');
				$(this).click(function(){
					var newitem=blankdcarow.clone();
					newitem.find('select[name="dcatype[]"]').replaceWith((row.find('select[name="dcatype[]"]').clone()));
					newitem.find('div:first-child').click(function(){
						removedca($(this).parent('div'),false);
					});
					binddcarow(newitem);
					row.before(newitem);
				});
			}
			binddcarow($(this).parent('div'));
		});

                function removedca(row,lookup){
		  if(!lookup) {
			row.remove();
		  } else {
			$.post('',{dcaused: row.find('div:nth-child(2) input').attr('data'), remove: ''}).done(function(data){
				if(data.trim()==0){
					row.effect('explode', {}, 500, function(){
						$(this).remove();
					});
				}else if(data.trim()=="fail") {
					var cancelbutton={
						"<?php echo __("Cancel"); ?>": function(){
							$(this).dialog("destroy");
						}
					}
<?php echo "				var modal=$('<div />', {id: 'modal', title: '".__("Custom Device Attribute Delete Error")."'}).html('<div id=\"modaltext\">AAAAAAAAAAHHHHHHHHHH!!!  *crash* *fire* *chaos*<br><br>".__("Something just went horribly wrong.")."</div>').dialog({"; ?>
					dialogClass: 'no-close',
					appendTo: 'body',
					modal: true,
					buttons: $.extend({}, cancelbutton)
					});

				}else{
					var defaultbutton={
						"<?php echo __("Delete from All Devices/Templates"); ?>": function(){
							$.post('',{dcaid: row.find('div:nth-child(2) input').attr('data'),dcal: '', dcar: '', dcaa: '', dcav: '', clear: ''}).done(function(data){
								if(data.trim()=='u'){ // success
									$('#modal').dialog("destroy");
									row.effect('explode', {}, 500, function(){
										$(this).remove();
									});
								}else{ // failed to delete
									$('#modaltext').html('AAAAAAAAAAHHHHHHHHHH!!!  *crash* *fire* *chaos*<br><br><?php echo __("Something just went horribly wrong."); ?>');
									$('#modal').dialog('option','buttons',cancelbutton);
								}
							});
						}
					}
					var cancelbutton={
						"<?php echo __("Cancel"); ?>": function(){
							$(this).dialog("destroy");
						}
					}
<?php echo "				var modal=$('<div />', {id: 'modal', title: '".__("Custom Device Attribute Delete Override")."'}).html('<div id=\"modaltext\">".__("This custom device attribute is in use somewhere. If you choose to delete the attribute, it will be removed from all devices and device templates.")."</div>').dialog({"; ?>
					dialogClass: 'no-close',
					appendTo: 'body',
					modal: true,
					buttons: $.extend({}, defaultbutton, cancelbutton)
                                        });
                                }
                        });
		  }
                }

		// Reporting - Utilities

		$('input[id^="snmp"],input[id="cut"],input[id="dot"],input[id="ipmitool"]').each(function(){
			var a=$(this);
			var icon=$('<span>',{style: 'float:right;margin-top:5px;'}).addClass('ui-icon').addClass('ui-icon-info');
			a.parent('div').append(icon);
			$(this).keyup(function(){
				var b=a.next('span');
				$.post('',{fe: $(this).val()}).done(function(data){
					if(data==1){
						a.effect('highlight', {color: 'lightgreen'}, 1500);
						b.addClass('ui-icon-circle-check').removeClass('ui-icon-info').removeClass('ui-icon-circle-close');
					}else{
						a.effect('highlight', {color: 'salmon'}, 1500);
						b.addClass('ui-icon-circle-close').removeClass('ui-icon-info').removeClass('ui-icon-circle-check');
					}
				});
			});
			$(this).trigger('keyup');
		});

		// Convert this bitch over to an ajax form submit
		$('button[name="action"]').click(function(e){
			// Clear the messages blank
			$('#messages').text('');
			// Don't let this button do a real form submit
			e.preventDefault();
			// Collect the config data
			var formdata=$(".main form").serializeArray();
			// Set the action of the form to Update
			formdata.push({name:'action',value:"Update"});
			// Post the config data then update the status message
			$.post('',formdata).done(function(){$('#messages').text('Updated');}).error(function(){$('#messages').text('Something is broken');});
		});

		$('.main form').submit(function(e){
			e.preventDefault();
		});

		// Make all the selects 100% width
		sheet.insertRule(".config .main select { width: 100%; }", 0);
	});

	// Making it to where I can add a rule to make the config page look nicer
	var sheet=(function() {
		var style = document.createElement("style");
		style.appendChild(document.createTextNode(""));
		document.head.appendChild(style);
		return style.sheet;
	})();

	// File upload
	function reload() {
		$.get('configuration.php?fl&json').done(function(data){
			var filelist=$('#filelist');
			filelist.html('');
			for(var f in data){
				filelist.append($('<span>').text(data[f]));
			}
			bindevents();
		});
	}
	function bindevents() {
		$("#imageselection span").each(function(){
			var preview=$('#imageselection #preview');
			$(this).click(function(){
				preview.css({'border-width': '5px', 'width': '380px', 'height': '380px'});
				preview.html('<img src="images/'+$(this).text()+'" alt="preview">').attr('image',$(this).text());
				preview.children('img').load(function(){
					var topmargin=0;
					var leftmargin=0;
					if($(this).height()<$(this).width()){
						$(this).width(preview.innerHeight());
						$(this).css({'max-width': preview.innerWidth()+'px'});
						topmargin=Math.floor((preview.innerHeight()-$(this).height())/2);
					}else{
						$(this).height(preview.innerHeight());
						$(this).css({'max-height': preview.innerWidth()+'px'});
						leftmargin=Math.floor((preview.innerWidth()-$(this).width())/2);
					}
					$(this).css({'margin-top': topmargin+'px', 'margin-left': leftmargin+'px'});
				});
				$("#imageselection span").each(function(){
					$(this).removeAttr('style');
				});
				$(this).css({'border':'1px dotted black','background-color':'#eeeeee'});
				$('#header').css('background-image', 'url("images/'+$(this).text()+'")');
			});
			if($($("#imageselection").data('input')).val()==$(this).text()){
				$(this).click();
				this.parentNode.scrollTop=(this.offsetTop - (this.parentNode.clientHeight / 2) + (this.scrollHeight / 2) );
			}
		});
	}
	function uploadifive() {
		$('#dev_file_upload').uploadifive({
			'formData' : {
					'timestamp' : '<?php echo $timestamp;?>',
					'token'     : '<?php echo $salt;?>',
					'dir'		: 'images'
				},
			'buttonText'		: 'Upload new image',
			'width'				: '150',
			'removeCompleted' 	: true,
			'checkScript'		: 'scripts/check-exists.php',
			'uploadScript'		: 'scripts/uploadifive.php',
			'onUploadComplete'	: function(file, data) {
				data=$.parseJSON(data);
				if(data.status=='1'){
					// something broke, deal with it
					var toast=$('<div>').addClass('uploadifive-queue-item complete');
					var close=$('<a>').addClass('close').text('X').click(function(){$(this).parent('div').remove();});
					var span=$('<span>');
					var error=$('<div>').addClass('border').css({'margin-top': '2px', 'padding': '3px'}).text(data.msg);
					toast.append(close);
					toast.append($('<div>').append(span.clone().addClass('filename').text(file.name)).append(span.clone().addClass('fileinfo').text(' - Error')));
					toast.append(error);
					$('#uploadifive-'+this[0].id+'-queue').append(toast);
				}else{
					$($("#imageselection").data('input')).val(file.name.replace(/\s/g,'_'));
					// fuck yeah, reload the file list
					reload($(this).data('dir'));
				}
			}
		});
	}
  </script>
</head>
<body>
<?php include( 'header.inc.php' ); ?>
<div class="page config">
<?php
	include( "sidebar.inc.php" );

echo '<div class="main">
<div class="center"><div>
<h3></h3><h3 id="messages"></h3>
<form enctype="multipart/form-data" action="',$_SERVER["PHP_SELF"],'" method="POST">
   <input type="hidden" name="Version" value="',$config->ParameterArray["Version"],'">

	<div id="configtabs">
		<ul>
			<li><a href="#general">',__("General"),'</a></li>
			<li><a href="#workflow">',__("Workflow"),'</a></li>
			<li><a href="#style">',__("Style"),'</a></li>
			<li><a href="#email">',__("Email"),'</a></li>
			<li><a href="#reporting">',__("Reporting"),'</a></li>
			<li><a href="#tt">',__("ToolTips"),'</a></li>
			<li><a href="#cc">',__("Cabling"),'</a></li>
			<li><a href="#dca">',__("Custom Device Attributes"),'</a></li>
			<li><a href="#preflight">',__("Pre-Flight Check"),'</a></li>
		</ul>
		<div id="general">
			<div class="table">
				<div>
					<div><label for="OrgName">',__("Organization Name"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["OrgName"],'" name="OrgName" value="',$config->ParameterArray["OrgName"],'"></div>
				</div>
				<div>
					<div><label for="Locale">',__("Locale"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["Locale"],'" name="Locale" value="',$config->ParameterArray["Locale"],'"></div>
				</div>
				<div>
					<div><label for="DefaultPanelVoltage">',__("Default Panel Voltage"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["DefaultPanelVoltage"],'" name="DefaultPanelVoltage" value="',$config->ParameterArray["DefaultPanelVoltage"],'"></div>
				</div>
			</div> <!-- end table -->
			<h3>',__("Time and Measurements"),'</h3>
			<div class="table" id="timeandmeasurements">
				<div>
					<div><label for="timezone">',__("Time Zone"),'</label></div>
					<div><input type="text" readonly="readonly" id="timezone" defaultvalue="',$config->defaults["timezone"],'" name="timezone" value="',$config->ParameterArray["timezone"],'"></div>
				</div>
				<div>
					<div><label for="mDate">',__("Manufacture Date"),'</label></div>
					<div><select id="mDate" name="mDate" defaultvalue="',$config->defaults["mDate"],'" data="',$config->ParameterArray["mDate"],'">
							<option value="blank"',(($config->ParameterArray["mDate"]=="blank")?' selected="selected"':''),'>',__("Blank"),'</option>
							<option value="now"',(($config->ParameterArray["mDate"]=="now")?' selected="selected"':''),'>',__("Now"),'</option>
						</select>
					</div>
				</div>
				<div>
					<div><label for="wDate">',__("Warranty Date"),'</label></div>
					<div><select id="wDate" name="wDate" defaultvalue="',$config->defaults["wDate"],'" data="',$config->ParameterArray["wDate"],'">
							<option value="blank"',(($config->ParameterArray["wDate"]=="blank")?' selected="selected"':''),'>',__("Blank"),'</option>
							<option value="now"',(($config->ParameterArray["wDate"]=="now")?' selected="selected"':''),'>',__("Now"),'</option>
						</select>
					</div>
				</div>
				<div>
					<div><label for="mUnits">',__("Measurement Units"),'</label></div>
					<div><select id="mUnits" name="mUnits" defaultvalue="',$config->defaults["mUnits"],'" data="',$config->ParameterArray["mUnits"],'">
							<option value="english"',(($config->ParameterArray["mUnits"]=="english")?' selected="selected"':''),'>',__("English"),'</option>
							<option value="metric"',(($config->ParameterArray["mUnits"]=="metric")?' selected="selected"':''),'>',__("Metric"),'</option>
						</select>
					</div>
				</div>
				<div>
					<div><label for="PageSize">',__("Page Size"),'</label></div>
					<div><select id="PageSize" name="PageSize" defaultvalue="',$config->defaults["PageSize"],'" data="',$config->ParameterArray["PageSize"],'">
							<option value="A4"',(($config->ParameterArray["PageSize"]=="A4")?' selected="selected"':''),'>',__("A4"),'</option>
							<option value="A3"',(($config->ParameterArray["PageSize"]=="A3")?' selected="selected"':''),'>',__("A3"),'</option>
							<option value="Letter"',(($config->ParameterArray["PageSize"]=="Letter")?' selected="selected"':''),'>',__("Letter"),'</option>
							<option value="Legal"',(($config->ParameterArray["PageSize"]=="Legal")?' selected="selected"':''),'>',__("Legal"),'</option>
						</select>
					</div>
				</div>
			</div> <!-- end table -->
			<h3>',__("Users"),'</h3>
			<div class="table">
				<div>
					<div><label for="ClassList">',__("Department Types"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["ClassList"],'" name="ClassList" value="',$classlist,'"></div>
				</div>
				<div>
					<div><label for="UserLookupURL">',__("User Lookup URL"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["UserLookupURL"],'" name="UserLookupURL" value="',$config->ParameterArray["UserLookupURL"],'"></div>
				</div>
				<div>
					<div><label for="RequireDefinedUser">',__("Block Undefined Users"),'</label></div>
					<div><select id="RequireDefinedUser" name="RequireDefinedUser" defaultvalue="',$config->defaults["RequireDefinedUser"],'" data="',$config->ParameterArray["RequireDefinedUser"],'">
							<option value="disabled">',__("Disabled"),'</option>
							<option value="enabled">',__("Enabled"),'</option>
						</select>
					</div>
				</div>
			</div> <!-- end table -->
			<h3>',__("Rack Usage"),'</h3>
			<div class="table" id="rackusage">
				<div>
					<div><label for="SpaceRed">',__("Space Critical"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["SpaceRed"],'" name="SpaceRed" value="',$config->ParameterArray["SpaceRed"],'"></div>
					<div></div>
					<div><label for="TemperatureRed">',__("Temperature Critical"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["TemperatureRed"],'" name="TemperatureRed" value="',$config->ParameterArray["TemperatureRed"],'"></div>
				</div>
				<div>
					<div><label for="SpaceYellow">',__("Space Warning"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["SpaceYellow"],'" name="SpaceYellow" value="',$config->ParameterArray["SpaceYellow"],'"></div>
					<div></div>
					<div><label for="TemperatureYellow">',__("Temperature Warning"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["TemperatureYellow"],'" name="TemperatureYellow" value="',$config->ParameterArray["TemperatureYellow"],'"></div>
				</div>
				<div>
					<div><label for="WeightRed">',__("Weight Critical"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["WeightRed"],'" name="WeightRed" value="',$config->ParameterArray["WeightRed"],'"></div>
					<div></div>
					<div><label for="HumidityRedHigh">',__("High Humidity Critical"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["HumidityRedHigh"],'" name="HumidityRedHigh" value="',$config->ParameterArray["HumidityRedHigh"],'"></div>
				</div>
				<div>
					<div><label for="WeightYellow">',__("Weight Warning"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["WeightYellow"],'" name="WeightYellow" value="',$config->ParameterArray["WeightYellow"],'"></div>
					<div></div>
					<div><label for="HumidityRedLow">',__("Low Humidity Critical"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["HumidityRedLow"],'" name="HumidityRedLow" value="',$config->ParameterArray["HumidityRedLow"],'"></div>
				</div>
				<div>
					<div><label for="PowerRed">',__("Power Critical"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["PowerRed"],'" name="PowerRed" value="',$config->ParameterArray["PowerRed"],'"></div>
					<div></div>
					<div><label for="HumidityYellowHigh">',__("High Humidity Caution"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["HumidityYellowHigh"],'" name="HumidityYellowHigh" value="',$config->ParameterArray["HumidityYellowHigh"],'"></div>
				</div>
				<div>
					<div><label for="PowerYellow">',__("Power Warning"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["PowerYellow"],'" name="PowerYellow" value="',$config->ParameterArray["PowerYellow"],'"></div>
					<div></div>
					<div><label for="HumidityYellowLow">',__("Low Humidity Caution"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["HumidityYellowLow"],'" name="HumidityYellowLow" value="',$config->ParameterArray["HumidityYellowLow"],'"></div>
				</div>
				<div>
					<div><label for="RCIHigh">',__("RCI (Rack Cooling Index) High"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["RCIHigh"],'" name="RCIHigh" value="',$config->ParameterArray["RCIHigh"],'"></div>
					<div></div>
					<div><label for="RCILow">',__("RCI (Rack Cooling Index) Low"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["RCILow"],'" name="RCILow" value="',$config->ParameterArray["RCILow"],'"></div>
				</div>
			</div> <!-- end table -->
			<h3>',__("Virtual Machines"),'</h3>
			<div class="table" id="rackusage">
				<div>
					<div><label for="VMExpirationTime">',__("Expiration Time (Days)"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["VMExpirationTime"],'" name="VMExpirationTime" value="',$config->ParameterArray["VMExpirationTime"],'"></div>
				</div>
			</div> <!-- end table -->
			',$tzmenu,'
		</div>
		<div id="workflow">
			<div class="table">
				<div>
					<div><label for="WorkOrderBuilder">',__("Work Order Builder"),'</label></div>
					<div><select id="WorkOrderBuilder" name="WorkOrderBuilder" defaultvalue="',$config->defaults["WorkOrderBuilder"],'" data="',$config->ParameterArray["WorkOrderBuilder"],'">
							<option value="disabled">',__("Disabled"),'</option>
							<option value="enabled">',__("Enabled"),'</option>
						</select>
					</div>
				</div>
			</div> <!-- end table -->
			<h3>',__("Rack Requests"),'</h3>
			<div class="table">
				<div>
					<div><label for="RackRequests">',__("Rack Requests"),'</label></div>
					<div><select id="RackRequests" name="RackRequests" defaultvalue="',$config->defaults["RackRequests"],'" data="',$config->ParameterArray["RackRequests"],'">
							<option value="disabled">',__("Disabled"),'</option>
							<option value="enabled">',__("Enabled"),'</option>
						</select>
					</div>
				</div>
				<div>
					<div><label for="MailSubject">',__("Mail Subject"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["MailSubject"],'" name="MailSubject" value="',$config->ParameterArray["MailSubject"],'"></div>
				</div>
				<div>
					<div><label for="RackWarningHours">',__("Warning (Hours)"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["RackWarningHours"],'" name="RackWarningHours" value="',$config->ParameterArray["RackWarningHours"],'"></div>
				</div>
				<div>
					<div><label for="RackOverdueHours">',__("Critical (Hours)"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["RackOverdueHours"],'" name="RackOverdueHours" value="',$config->ParameterArray["RackOverdueHours"],'"></div>
				</div>
			</div> <!-- end table -->
			<h3>',__("Online Repository"),'</h3>
			<h5><u>',__("Default Behavior for Site (Can Override Per Template)"),'</u></h5>
			<div class="table" id="repository">
				<div>
					<div><label for="ShareToRepo">',__("Share your templates to the repository"),'</label></div>
					<div><select name="ShareToRepo" id="ShareToRepo" defaultvalue="',$config->defaults["ShareToRepo"],'" data="',$config->ParameterArray["ShareToRepo"],'">
						<option value="disabled">',__("Disabled"),'</option>
						<option value="enabled">',__("Enabled"),'</option>
						</select>
					</div>
				</div>
				<div>
					<div><label for="keep_local">',__("Keep local values when synchronizing"),'</label></div>
					<div><select name="KeepLocal" id="KeepLocal" defaultvalue="',$config->defaults["KeepLocal"],'" data="',$config->ParameterArray["KeepLocal"],'">
						<option value="disabled">',__("Disabled"),'</option>
						<option value="enabled">',__("Enabled"),'</option>
						</select>
					</div>
				</div>
				<div>
					<div><label for="APIUserID">',__("API UserID"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["APIUserID"],'" name="APIUserID" value="',$config->ParameterArray["APIUserID"],'"></div>
				</div>
				<div>
					<div><label for="APIKey">',__("API Key"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["APIKey"],'" name="APIKey" value="',$config->ParameterArray["APIKey"],'"></div>
				</div>
			</div>
		</div>
		<div id="style">
			<h3>',__("Racks & Maps"),'</h3>
			<div class="table">
				<div>
					<div><label for="CriticalColor">',__("Critical Color"),'</label></div>
					<div><div class="cp"><input type="text" class="color-picker" name="CriticalColor" value="',$config->ParameterArray["CriticalColor"],'"></div></div>
					<div><button type="button">&lt;--</button></div>
					<div><span>',strtoupper($config->defaults["CriticalColor"]),'</span></div>
				</div>
				<div>
					<div><label for="CautionColor">',__("Caution Color"),'</label></div>
					<div><div class="cp"><input type="text" class="color-picker" name="CautionColor" value="',$config->ParameterArray["CautionColor"],'"></div></div>
					<div><button type="button">&lt;--</button></div>
					<div><span>',strtoupper($config->defaults["CautionColor"]),'</span></div>
				</div>
				<div>
					<div><label for="GoodColor">',__("Good Color"),'</label></div>
					<div><div class="cp"><input type="text" class="color-picker" name="GoodColor" value="',$config->ParameterArray["GoodColor"],'"></div></div>
					<div><button type="button">&lt;--</button></div>
					<div><span>',strtoupper($config->defaults["GoodColor"]),'</span></div>
				</div>
				<div>
					<div>&nbsp;</div>
					<div></div>
					<div></div>
					<div></div>
				</div>
				<div>
					<div><label for="Phase1Color">',__("Phase 1 Color"),'</label></div>
					<div><div class="cp"><input type="text" class="color-picker" name="Phase1Color" value="',$config->ParameterArray["Phase1Color"],'"></div></div>
					<div><button type="button">&lt;--</button></div>
					<div><span>',strtoupper($config->defaults["Phase1Color"]),'</span></div>
				</div>
				<div>
					<div><label for="Phase2Color">',__("Phase 2 Color"),'</label></div>
					<div><div class="cp"><input type="text" class="color-picker" name="Phase2Color" value="',$config->ParameterArray["Phase2Color"],'"></div></div>
					<div><button type="button">&lt;--</button></div>
					<div><span>',strtoupper($config->defaults["Phase2Color"]),'</span></div>
				</div>
				<div>
					<div><label for="Phase3Color">',__("Phase 3 Color"),'</label></div>
					<div><div class="cp"><input type="text" class="color-picker" name="Phase3Color" value="',$config->ParameterArray["Phase3Color"],'"></div></div>
					<div><button type="button">&lt;--</button></div>
					<div><span>',strtoupper($config->defaults["Phase3Color"]),'</span></div>
				</div>
				<div>
					<div>&nbsp;</div>
					<div></div>
					<div></div>
					<div></div>
				</div>
				<div>
					<div><label for="ReservedColor">',__("Reserved Devices"),'</label></div>
					<div><div class="cp"><input type="text" class="color-picker" name="ReservedColor" value="',$config->ParameterArray["ReservedColor"],'"></div></div>
					<div><button type="button">&lt;--</button></div>
					<div><span>',strtoupper($config->defaults["ReservedColor"]),'</span></div>
				</div>
				<div>
					<div><label for="FreeSpaceColor">',__("Unused Spaces"),'</label></div>
					<div><div class="cp"><input type="text" class="color-picker" name="FreeSpaceColor" value="',$config->ParameterArray["FreeSpaceColor"],'"></div></div>
					<div><button type="button">&lt;--</button></div>
					<div><span>',strtoupper($config->defaults["FreeSpaceColor"]),'</span></div>
				</div>
			</div> <!-- end table -->
			<h3>',__("Devices"),'</h3>
			<div class="table">
				<div>
					<div><label for="LabelCase">',__("Device Labels"),'</label></div>
					<div><select id="LabelCase" name="LabelCase" defaultvalue="',$config->defaults["LabelCase"],'" data="',$config->ParameterArray["LabelCase"],'">
							<option value="upper">',transform(__("Uppercase"),'upper'),'</option>
							<option value="lower">',transform(__("Lowercase"),'lower'),'</option>
							<option value="initial">',transform(__("Initial caps"),'initial'),'</option>
							<option value="none">',__("Don't touch my labels"),'</option>
						</select>
					</div>
				</div>
				<div>
					<div><label for="AppendCabDC">',__("Device Lists"),'</label></div>
					<div><select id="AppendCabDC" name="AppendCabDC" defaultvalue="',$config->defaults["AppendCabDC"],'" data="',$config->ParameterArray["AppendCabDC"],'">
							<option value="disabled">',__("Just Devices"),'</option>
							<option value="enabled">',__("Show Datacenter and Cabinet"),'</option>
						</select>
					</div>
				</div>
			</div> <!-- end table -->
			<h3>',__("Site"),'</h3>
			<div class="table">
				<div>
					<div><label for="HeaderColor">',__("Header Color"),'</label></div>
					<div><div class="cp"><input type="text" class="color-picker" name="HeaderColor" value="',$config->ParameterArray["HeaderColor"],'"></div></div>
					<div><button type="button">&lt;--</button></div>
					<div><span>',strtoupper($config->defaults["HeaderColor"]),'</span></div>
				</div>
				<div>
					<div><label for="BodyColor">',__("Body Color"),'</label></div>
					<div><div class="cp"><input type="text" class="color-picker" name="BodyColor" value="',$config->ParameterArray["BodyColor"],'"></div></div>
					<div><button type="button">&lt;--</button></div>
					<div><span>',strtoupper($config->defaults["BodyColor"]),'</span></div>
				</div>
				<div>
					<div><label for="LinkColor">',__("Link Color"),'</label></div>
					<div><div class="cp"><input type="text" class="color-picker" name="LinkColor" value="',$config->ParameterArray["LinkColor"],'"></div></div>
					<div><button type="button">&lt;--</button></div>
					<div><span>',strtoupper($config->defaults["LinkColor"]),'</span></div>
				</div>
				<div>
					<div><label for="VisitedLinkColor">',__("Viewed Link Color"),'</label></div>
					<div><div class="cp"><input type="text" class="color-picker" name="VisitedLinkColor" value="',$config->ParameterArray["VisitedLinkColor"],'"></div></div>
					<div><button type="button">&lt;--</button></div>
					<div><span>',strtoupper($config->defaults["VisitedLinkColor"]),'</span></div>
				</div>
			</div> <!-- end table -->
		</div>
		<div id="email">
			<div class="table">
				<div>
					<div><label for="SMTPServer">',__("SMTP Server"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["SMTPServer"],'" name="SMTPServer" value="',$config->ParameterArray["SMTPServer"],'"></div>
				</div>
				<div>
					<div><label for="SMTPPort">',__("SMTP Port"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["SMTPPort"],'" name="SMTPPort" value="',$config->ParameterArray["SMTPPort"],'"></div>
				</div>
				<div>
					<div><label for="SMTPHelo">',__("SMTP Helo"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["SMTPHelo"],'" name="SMTPHelo" value="',$config->ParameterArray["SMTPHelo"],'"></div>
				</div>
				<div>
					<div><label for="SMTPUser">',__("SMTP Username"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["SMTPUser"],'" name="SMTPUser" value="',$config->ParameterArray["SMTPUser"],'"></div>
				</div>
				<div>
					<div><label for="SMTPPassword">',__("SMTP Password"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["SMTPPassword"],'" name="SMTPPassword" value="',$config->ParameterArray["SMTPPassword"],'"></div>
				</div>
				<div>
					<div><label for="MailToAddr">',__("Mail To"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["MailToAddr"],'" name="MailToAddr" value="',$config->ParameterArray["MailToAddr"],'"></div>
				</div>
				<div>
					<div><label for="MailFromAddr">',__("Mail From"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["MailFromAddr"],'" name="MailFromAddr" value="',$config->ParameterArray["MailFromAddr"],'"></div>
				</div>
				<div>
					<div><label for="ComputerFacMgr">',__("Facility Manager"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["ComputerFacMgr"],'" name="ComputerFacMgr" value="',$config->ParameterArray["ComputerFacMgr"],'"></div>
				</div>
				<div>
					<div><label for="FacMgrMail">',__("Facility Manager Email"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["FacMgrMail"],'" name="FacMgrMail" value="',$config->ParameterArray["FacMgrMail"],'"></div>
				</div>
			</div> <!-- end table -->
		</div>
		<div id="reporting">
			<div id="imageselection" title="Image file selector">
				',$imageselect,'
			</div>
			<div class="table">
				<div>
					<div><label for="annualCostPerUYear">',__("Annual Cost Per Rack Unit (Year)"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["annualCostPerUYear"],'" name="annualCostPerUYear" value="',$config->ParameterArray["annualCostPerUYear"],'"></div>
				</div>
				<div>
					<div><label for="annualCostPerWattYear">',__("Annual Cost Per Watt (Year)"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["annualCostPerWattYear"],'" name="annualCostPerWattYear" value="',$config->ParameterArray["annualCostPerWattYear"],'"></div>
				</div>
				<div>
					<div><label for="PDFLogoFile">',__("Logo file for headers"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["PDFLogoFile"],'" name="PDFLogoFile" value="',$config->ParameterArray["PDFLogoFile"],'"></div>
				</div>
				<div>
					<div><label for="PDFfont">',__("Font"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["PDFfont"],'" name="PDFfont" value="',$config->ParameterArray["PDFfont"],'" title="examples: courier, DejaVuSans, helvetica, OpenSans-Bold, OpenSans-Cond, times"></div>
				</div>
				<div>
					<div><label for="NewInstallsPeriod">',__("New Installs Period"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["NewInstallsPeriod"],'" name="NewInstallsPeriod" value="',$config->ParameterArray["NewInstallsPeriod"],'"></div>
				</div>
				<div>
					<div><label for="InstallURL">',__("Base URL for install"),'</label></div>
					<div><input type="text" defaultvalue="',$href,'" name="InstallURL" value="',$config->ParameterArray["InstallURL"],'"></div>
				</div>
			</div> <!-- end table -->
			<h3>',__("SNMP Options"),'</h3>
			<div class="table">
				<div>
					<div><label for="SNMPCommunity">',__("Default SNMP Community"),'</label></div>
					<div><input type="password" defaultvalue="',$config->defaults["SNMPCommunity"],'" name="SNMPCommunity" value="',$config->ParameterArray["SNMPCommunity"],'"></div>
				</div>
				<div>
				  <div><label for="SNMPVersion">'.__("SNMP Version").'</label></div>
				  <div>
						<select id="SNMPVersion" defaultvalue="',$config->defaults["SNMPVersion"],'" name="SNMPVersion" data="',$config->ParameterArray["SNMPVersion"],'">
							<option value="1">1</option>
							<option value="2c">2c</option>
							<option value="3">3</option>
						</select>
					</div>
				</div>
				<div>
				  <div><label for="v3SecurityLevel">'.__("SNMPv3 Security Level").'</label></div>
				  <div>
					<select id="v3SecurityLevel" defaultvalue="',$config->defaults["v3SecurityLevel"],'" name="v3SecurityLevel" data="',$config->ParameterArray["v3SecurityLevel"],'">
						<option value="noAuthNoPriv">noAuthNoPriv</option>
						<option value="authNoPriv">authNoPriv</option>
						<option value="authPriv">authPriv</option>
					</select>
				  </div>
				</div>
				<div>
				  <div><label for="v3AuthProtocol">'.__("SNMPv3 AuthProtocol").'</label></div>
					<div>
						<select id="v3AuthProtocol" defaultvalue="',$config->defaults["v3AuthProtocol"],'" name="v3AuthProtocol" data="',$config->ParameterArray["v3AuthProtocol"],'">
							<option value="MD5">MD5</option>
							<option value="SHA">SHA</option>
						</select>
					</div>
				</div>
				<div>
				  <div><label for="v3AuthPassphrase">'.__("SNMPv3 Passphrase").'</label></div>
				  <div><input type="password" defaultvalue="',$config->defaults["v3AuthPassphrase"],'" name="v3AuthPassphrase" id="v3AuthPassphrase" value="',$config->ParameterArray["v3AuthPassphrase"],'"></div>
				</div>
				<div>
				  <div><label for="v3PrivProtocol">'.__("SNMPv3 PrivProtocol").'</label></div>
				  <div>
					<select id="v3PrivProtocol" defaultvalue="',$config->defaults["v3PrivProtocol"],'" name="v3PrivProtocol" data="',$config->ParameterArray["v3PrivProtocol"],'">
						<option value="DES">DES</option>
						<option value="AES">AES</option>
					</select>
				  </div>
				</div>
				<div>
				  <div><label for="v3PrivPassphrase">'.__("SNMPv3 PrivPassphrase").'</label></div>
				  <div><input type="password" defaultvalue="',$config->defaults["v3PrivPassphrase"],'" name="v3PrivPassphrase" id="v3PrivPassphrase" value="',$config->ParameterArray["v3PrivPassphrase"],'"></div>
				</div>
			</div> <!-- end table -->
			<h3>',__("Capacity Reporting"),'</h3>
			<div class="table">
				<div>
					<div><label for="NetworkCapacityReportOptIn">',__("Switches"),'</label></div>
					<div>
						<select id="NetworkCapacityReportOptIn" defaultvalue="',$config->defaults["NetworkCapacityReportOptIn"],'" name="NetworkCapacityReportOptIn" data="',$config->ParameterArray["NetworkCapacityReportOptIn"],'">
							<option value="OptIn">',__("OptIn"),'</option>
							<option value="OptOut">',__("OptOut"),'</option>
						</select>
					</div>
				</div>
				<div>
					<div><label for="NetworkThreshold">',__("Switch Capacity Threshold"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["NetworkThreshold"],'" name="NetworkThreshold" value="',$config->ParameterArray["NetworkThreshold"],'"></div>
				</div>
			</div>
			<h3>',__("Utilities"),'</h3>
			<div class="table">
				<div>
					<div><label for="snmpget">',__("snmpget"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["snmpget"],'" name="snmpget" value="',$config->ParameterArray["snmpget"],'"></div>
				</div>
				<div>
					<div><label for="snmpwalk">',__("snmpwalk"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["snmpwalk"],'" name="snmpwalk" value="',$config->ParameterArray["snmpwalk"],'"></div>
				</div>
				<div>
					<div><label for="cut">',__("cut"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["cut"],'" name="cut" value="',$config->ParameterArray["cut"],'"></div>
				</div>
				<div>
					<div><label for="dot">',__("dot"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["dot"],'" name="dot" value="',$config->ParameterArray["dot"],'"></div>
				</div>
				<div>
                                        <div><label for="ipmitool">',__("ipmitool"),'</label></div>
                                        <div><input type="text" defaultvalue="',$config->defaults["ipmitool"],'" name="ipmitool" value="',$config->ParameterArray["ipmitool"],'"></div>
                                </div>
			</div> <!-- end table -->
			<h3>',__("Graph Dates"),'</h3>
                        <div class="table">
                                <div>
                                        <div><label for="TimeInterval">',__("Time Interval"),'</label></div>
                                        <div><select defaultvalue="',$config->defaults["TimeInterval"],'" name="TimeInterval" data="',$config->ParameterArray["TimeInterval"],'">
                                                        <option value="Last 7 Days">',__("Last 7 Days"),'</option>
                                                        <option value="Last Month">',__("Last Month"),'</option>
                                                        <option value="Last Year">',__("Last Year"),'</option>
                                                </select>
                                        </div>
                                </div>
                        </div>
			<h3>',__("Measure Parameters"),'</h3>
                        <div class="table">
                                <div>
                                        <div><label for="TimeInterval">',__("Days Before Compression"),'</label></div>
                                        <div><input type="number" defaultvalue="',$config->defaults["DaysBeforeCompression"],'" name="DaysBeforeCompression" min="0" value="',$config->ParameterArray["DaysBeforeCompression"],'"></div>
                                </div>
                        </div>

		</div>
		<div id="tt">
			<div class="table">
				<div>
					<div><label for="ToolTips">',__("Cabinet ToolTips"),'</label></div>
					<div><select id="ToolTips" name="ToolTips" defaultvalue="',$config->defaults["ToolTips"],'" data="',$config->ParameterArray["ToolTips"],'">
							<option value="disabled">',__("Disabled"),'</option>
							<option value="enabled">',__("Enabled"),'</option>
						</select>
					</div>
				</div>
			</div> <!-- end table -->
			<br>
			',$tooltip,'
			<br>
			<div class="table">
				<div>
					<div><label for="CDUToolTips">',__("CDU ToolTips"),'</label></div>
					<div><select id="CDUToolTips" name="CDUToolTips" defaultvalue="',$config->defaults["CDUToolTips"],'" data="',$config->ParameterArray["CDUToolTips"],'">
							<option value="disabled">',__("Disabled"),'</option>
							<option value="enabled">',__("Enabled"),'</option>
						</select>
					</div>
				</div>
			</div> <!-- end table -->
			<br>
			',$cdutooltip,'
		</div>
		<div id="cc">
			<h3>',__("Media Types"),'</h3>
			<div class="table">
				<div>
					<!-- <div><label for="MediaEnforce">',__("Media Type Matching"),'</label></div>
					<div><select id="MediaEnforce" name="MediaEnforce" defaultvalue="',$config->defaults["MediaEnforce"],'" data="',$config->ParameterArray["MediaEnforce"],'">
							<option value="disabled">',__("Disabled"),'</option>
							<option value="enabled">',__("Enforce"),'</option>
						</select>
					</div> -->
					<input type="hidden" name="MediaEnforce" value="disabled">
				</div>
			</div> <!-- end table -->
			<br>
			<div class="table" id="mediatypes">
				<div>
					<div></div>
					<div>',__("Media Type"),'</div>
					<div>',__("Default Color"),'</div>
				</div>
				',$mediatypes,'
				<div>
					<div id="newline"><img title="',__("Add new row"),'" src="images/add.gif"></div>
					<div><input type="text" name="mediatype[]"></div>
					<div>',$colorselector,'</div>
				</div>
			</div> <!-- end table -->
			<h3>',__("Cable Colors"),'</h3>
			<div class="table" id="cablecolor">
				<div>
					<div></div>
					<div>',__("Color"),'</div>
					<div>',__("Default Note"),'</div>
				</div>
				',$cablecolors,'
				<div>
					<div id="newline"><img title="',__("Add new row"),'" src="images/add.gif"></div>
					<div><input type="text" name="colorcode[]"></div>
					<div><input type="text" name="ccdefaulttext[]"></div>
				</div>
			</div> <!-- end table -->
			<h3>',__("Connection Pathing"),'</h3>
			<div class="table" id="pathweights">
				<div>
					<div><label for="path_weight_cabinet">',__("Cabinet Weight"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["path_weight_cabinet"],'" name="path_weight_cabinet" value="',$config->ParameterArray["path_weight_cabinet"],'"></div>
				</div>
				<div>
					<div><label for="path_weight_rear">',__("Weight for rear connections between panels"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["path_weight_rear"],'" name="path_weight_rear" value="',$config->ParameterArray["path_weight_rear"],'"></div>
				</div>
				<div>
					<div><label for="path_weight_row">',__("Weight for patches in the same row"),'</label></div>
					<div><input type="text" defaultvalue="',$config->defaults["path_weight_row"],'" name="path_weight_row" value="',$config->ParameterArray["path_weight_row"],'"></div>
				</div>
			</div> <!-- end table -->
		</div>
		<div id="dca">
			<h3>',__("Custom Device Attributes"),'</h3>
			<div class="table" id="customattrs">
				<div>
					<div></div>
					<div class="customattrsheader">',__("Label"),'</div>
					<div class="customattrsheader">',__("Type"),'</div>
					<div class="customattrsheader">',__("Required"),'</div>
					<div class="customattrsheader">',__("Apply to<br>All Devices"),'</div>
					<div class="customattrsheader">',__("Default Value"),'</div>
				</div>
				',$customattrs,'
				<div>
					<div id="newline"><img title="',__("Add new row"),'" src="images/add.gif"></div>
					<div><input type="text" name="dcalabel[]"></div>
					<div>',$dcaTypeSelector,'</div>
					<div><input type="checkbox" name="dcarequired[]"></div>
					<div><input type="checkbox" name="dcaalldevices[]"></div>
					<div><input type="text" name="dcavalue[]"></div>
				</div>
			</div>

		</div>
		<div id="preflight">
			<iframe src="preflight.inc.php"></iframe>
		</div><!-- end preflight tab -->
	</div>';

?>

<div class="table centermargin">
<div>
	<div>&nbsp;</div>
</div>
<div>
   <?php echo '<button type="submit" name="action" value="Update">',__("Update"),'</button></div>'; ?>
</div>
</div> <!-- END div.table -->
</form>
</div>
   <?php echo '<a href="index.php">[ ',__("Return to Main Menu"),' ]</a>'; ?>
</div>
  </div>
  </div>
</body>
</html>
