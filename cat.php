<?php
include ("inc/cookie.php");
include ("inc/db.php");
include ("inc/form.php");
include ("inc/crypt.php");

$page="cat";

$db=sql_conn();

$auth=checkcookies($db);

if (!$auth) {
	header("Location: index.php");
	die();
}

$userid=$_COOKIE["id"];
$key=$_COOKIE["key"];

$action=gorp("action", false);

if (!$action) $action="view";

$output="";

switch($action) {
	case "delete":
		$catid=gorp("catid",0);
		$itemid=gorp("itemid",0);
		
		if (!empty($itemid)) {
			$result=mysqli_query($db, "select site,iv from logins where id = \"$itemid\" and userid=\"$userid\"");
			if (mysqli_num_rows($result)==1) {
				$row=mysqli_fetch_assoc($result);
				$output.="<P CLASS=\"plain\">\n";
				$output.="Confirm delete for site: " .htmlspecialchars(trim(decrypt($key,base64_decode($row["site"]),base64_decode($row["iv"])))) ."?\n";
				$output.="<P CLASS=\"plain\">\n";
				$output.=form_begin($_SERVER["PHP_SELF"],"POST","delete_db");
				$output.=input_hidden("action","delete_db");
				$output.=input_hidden("catid",$catid);
				$output.=input_hidden("itemid",$itemid);
				$output.=submit("Delete");
				$output.=input_button("Cancel","window.location.href = 'cat.php?catid=".$catid."'");
				$output.=form_end();
			} else {
				header("Location: cat.php?catid=".$catid);
			}
		} else {
			header("Location: cat.php?catid=".$catid);
		}
	break;

	case "delete_db":
		$catid=gorp("catid",0);
		$itemid=gorp("itemid",0);
		mysqli_query($db, "delete from logins where id = \"$itemid\" and userid = \"$userid\"");
		header("Location: cat.php?catid=".$catid);
	break;
	case "view":
		$catid=gorp("catid","all");
		if ($catid != "all" && !is_numeric($catid)) {
                        $catid="all";
                }
		$query = "select l.id, l.iv, l.login, l.password, l.site, l.url, c.title from logins l join cat c on l.catid = c.id where l.userid = \"$userid\"";
		if ($catid != "all") {
			$query .= " and catid = \"$catid\"";
		}

		$result=mysqli_query($db, $query);

		if (mysqli_num_rows($result)==0) {
			$output.="<SPAN CLASS=\"plain\">No data found</SPAN>";
		} else {
			
			$output.="<TABLE BORDER=\"0\" CELLPADDING=\"2\" CELLSPACING=\"1\">\n";
			$output.="<TR>\n";
			if ($catid=="all") {
				$output.="<TD CLASS=\"header\" WIDTH=\"80\"><a href=\"cat.php?catid=all&sort=category\">Category</a></TD>\n";
			}
			$output.="<TD CLASS=\"header\" WIDTH=\"300\"><a href=\"cat.php?catid=".$catid."&sort=site\">Site</a></TD>\n";
			$output.="<TD CLASS=\"header\" WIDTH=\"200\"><a href=\"cat.php?catid=".$catid."&sort=login\">Login</a></TD>\n";
			$output.="<TD CLASS=\"header\" WIDTH=\"120\">Password</TD>\n";
			$output.="<TD CLASS=\"header\" WIDTH=\"80\">Action</TD>\n";
			$output.="</TR>\n";

			while ($row=mysqli_fetch_assoc($result)) {
				$login=htmlspecialchars(trim(decrypt($key,base64_decode($row["login"]),base64_decode($row["iv"]))));
				$password=htmlspecialchars(trim(decrypt($key,base64_decode($row["password"]),base64_decode($row["iv"]))));
				$site=htmlspecialchars(trim(decrypt($key,base64_decode($row["site"]),base64_decode($row["iv"]))));
				$url=trim(decrypt($key,base64_decode($row["url"]),base64_decode($row["iv"])));
				$thisrow=array("id"=>$row["id"], "login"=>$login, "password"=>$password, "site"=>$site, "url"=>$url, "category"=>$row["title"]);
				$resarray[]=$thisrow;
				$valid_sort_fields=array("login","site","category");
				$sort=gorp("sort","site");
				if (!in_array($sort, $valid_sort_fields)) {
					$sort="site";
				}
				$sortarray[]=$thisrow[$sort];
			}

			array_multisort($sortarray, SORT_ASC, $resarray);

			foreach ($resarray as $val) {
				if (strlen($val["url"])>1) $outsite="<A HREF=\"".$val["url"]."\" TARGET=\"_blank\">".$val["site"]."</A>";
				else $outsite=$val["site"];
				$output.="<TR>";
				if ($catid=="all") {
					$output.="<TD CLASS=\"row\">".$val["category"]."</TD>\n";	
				}
				$output.="<TD CLASS=\"row\">".$outsite."</TD>\n";
				$output.="<TD CLASS=\"row\">".$val["login"]."</TD>\n";
				$output.="<TD OnMouseOver=\"this.style.color='#000000'\" OnMouseOut=\"this.style.color='#fdfed0'\" CLASS=\"password\">".$val["password"]."</TD>\n";
				$output.="<TD CLASS=\"row\"><A HREF=\"".$_SERVER["PHP_SELF"]."?action=edit&itemid=".$val["id"]."\">Edit</A> | <A HREF=\"".$_SERVER["PHP_SELF"]."?action=delete&itemid=".$val["id"]."&catid=".$catid."\">Delete</A></TD>\n";
				$output.="</TR>";
			}
			$output.="</TABLE>\n";
		}
	break;
	case "edit":
		$itemid=gorp("itemid",0);
		if ($itemid!=0) {
			//Get existing data and decrypt it first.
			$result=mysqli_query($db, "select id, iv, catid, login, password, site, url from logins where id = \"$itemid\" and userid=\"$userid\"");
			if (mysqli_num_rows($result)==1) {
				$row=mysqli_fetch_assoc($result);
				$catid=$row["catid"];
				$login=trim(decrypt($key,base64_decode($row["login"]),base64_decode($row["iv"])));
				$password=trim(decrypt($key,base64_decode($row["password"]),base64_decode($row["iv"])));
				$site=trim(decrypt($key,base64_decode($row["site"]),base64_decode($row["iv"])));
				$url=trim(decrypt($key,base64_decode($row["url"]),base64_decode($row["iv"])));
			} else {
				$output="<SPAN CLASS=\"error\">No permission to edit this entry</DIV><P>";
				$login="";
				$password="";
				$site="";
				$url="";
			}
		} else {
			$catid=gorp("catid",0);
			$login="";
			$password="";
			$site="";
			$url="";
		}

		// Get categories.
		$result=mysqli_query($db, "select id, title from cat where userid = \"$userid\" order by title");
		if (mysqli_num_rows($result)==0) {
			$output.="You must create some categories first";
		} else {
			$cats=restoarray($result);

			$output.=form_begin($_SERVER["PHP_SELF"],"POST");
			$output.=input_hidden("itemid",$itemid);
			$output.=input_hidden("action","save");
			$output.="<TABLE BORDER=\"0\" CELLPADDING=\"2\" CELLSPACING=\"0\">\n";
			$output.="<TR><TD CLASS=\"plain\">Category: </TD><TD CLASS=\"plain\">".input_select("catid",$catid,$cats)."</TD></TR>\n";
			$output.="<TR><TD CLASS=\"plain\">Site: </TD><TD CLASS=\"plain\">".input_text("site",30,255,$site)."</TD></TR>\n";
			$output.="<TR><TD CLASS=\"plain\">URL: </TD><TD CLASS=\"plain\">".input_text("url",30,255,$url)."</TD></TR>\n";
			$output.="<TR><TD CLASS=\"plain\">Login: </TD><TD CLASS=\"plain\">".input_text("login",30,255,$login)."</TD></TR>\n";
			$output.="<TR><TD CLASS=\"plain\">Password: </TD><TD CLASS=\"plain\">".input_text("password",30,255,$password)."</TD></TR>\n";
			$output.="<TR><TD CLASS=\"plain\" ALIGN=\"RIGHT\" COLSPAN=\"2\">".submit("Save entry")."</TD></TR>\n";
			$output.="</TABLE>\n";
			$output.=form_end();
		}
	break;

	case "save":
		$itemid=gorp("itemid",0);
		$catid=gorp("catid",0);
		$login=gorp("login","");
		$password=gorp("password","");
		$site=gorp("site","");
		$url=gorp("url","");

		if (strpos($url,"http://")===FALSE && strpos($url,"https://")===FALSE && !empty($url)) $url="http://".$url;

		// Encrypt login and pass using key.
		$iv=make_iv();
		$login=base64_encode(encrypt($key,$login,$iv));
		$password=base64_encode(encrypt($key,$password,$iv));
		$site=base64_encode(encrypt($key,$site,$iv));
		$url=base64_encode(encrypt($key,$url,$iv));
		$iv=base64_encode($iv);

		if ($itemid==0) {
			$query="insert logins values (NULL, \"$iv\", \"$userid\", \"$catid\", \"$login\", \"$password\", \"$site\", \"$url\")";
		} else {
			$query="update logins set iv = \"$iv\", catid=\"$catid\", login=\"$login\", password=\"$password\", site = \"$site\", url = \"$url\" where id = \"$itemid\" and userid=\"$userid\"";
		}
		mysqli_query($db, $query);
		header("Location: cat.php?catid=".$catid);
		die();
	break;
}



include ("inc/header.php");

echo $output;

include ("inc/footer.php");


