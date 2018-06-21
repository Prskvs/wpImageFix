<?php

function listFolderFiles($dir, $id = 0){
    echo '<ul class="files">';
    $i = $id;
    foreach(new DirectoryIterator($dir) as $file){
        if(!$file->isDot()){
            $pathname = $file->getPathname();
            $filename = $file->getFileName();			
			
			$li_start = '<li id="item-' . $i . '" class="' . ($file->isDir() ? 'folder' : 'file') . '">';
			$li_end = '</li>';
			$img_input = '<input id="' . $i . '" class="item-check" type="checkbox" name="image[]" value="' . str_replace('wp-content/uploads/', '', str_replace('\\', '/', $pathname)) . '" onchange="showOptions(); selectChildren(' . $i . ')" /><div class="item item-image"><div class="image"><a href="' . str_replace('\\', '/', $pathname) . '"><img src="' . $pathname . '" alt="' . $filename . '" width="200" /></a></div><div class="title"><a href="' . str_replace('\\', '/', $pathname) . '">' . $filename . '</a></div></div>';
			$file_input = '<input id="' . $i . '" class="item-check" type="checkbox" name="file[]" value="' . str_replace('wp-content/uploads/', '', str_replace('\\', '/', $pathname)) . '" onchange="showOptions(); selectChildren(' . $i . ')" /><div class="item item-file"><div class="title"><a href="' . str_replace('\\', '/', $pathname) . '">' . $filename . '</a></div></div>';
			$dir_input_start = '<input id="' . $i . '" class="item-check" type="checkbox" name="dir[]" value="' . str_replace('wp-content/uploads/', '', str_replace('\\', '/', $pathname)) . '" onchange="showOptions(); selectChildren(' . $i . ')" /><div class="item item-folder"><div class="title">' . $filename;
			$dir_input_end = '</div></div>';
			
            $i++;
            if($file->isFile())
				if(isset($_POST['png'])){
					if(@exif_imagetype($pathname) == IMAGETYPE_PNG)
						echo $li_start . $img_input . $li_end;
					}
				elseif(isset($_POST['jpg'])){
					if(@exif_imagetype($pathname) == IMAGETYPE_JPEG)
						echo $li_start . $img_input . $li_end;
					}
				else{
					switch(@exif_imagetype($pathname)){
						case IMAGETYPE_JPEG:
						case IMAGETYPE_PNG:
						case IMAGETYPE_GIF:
							echo $li_start . $img_input . $li_end;
							break;
						default:
							echo $li_start . $file_input . $li_end;
							break;
					}
				}
            elseif($file->isDir()){
                echo $li_start . $dir_input_start;
                $i = listFolderFiles($pathname, $i);
                echo $dir_input_end . $li_end;
            }
        }
    }
    echo '</ul>';
    return $i++;
}

function fieldCond($fields = [], $cond = '', $op = ','){
    $str = '';
    foreach($fields as $field)
        $str .= '`' . $field . '`' . $cond;

    return rtrim($str, $op);
}

function findEntries($images = [], $type = 'jpg', $imgpath = '/wp-content/uploads/'){
    require_once 'wp-config.php';

    if(!$con = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME)){
        die ('Cannot Connect.' . $con->error);
    }

    $fullpath = dirname((isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . $imgpath;

    $sql = '';
    $count = 0;
    foreach($images as $image){
        if(is_file(dirname(__FILE__) . $imgpath . $image)){
			$ext = pathinfo($image)['extension'];
            $sql .= "UPDATE `" . $table_prefix . "posts` SET `guid` = REPLACE(`guid`, '" . $image . "',  '" . str_replace($ext, $type, $image) . "'), `post_mime_type` = 'image/" . (($type === 'jpg') ? 'jpeg' : $type) . "' WHERE `guid` LIKE '%" . $image . "%';";
			$sql .= "UPDATE `" . $table_prefix . "posts` SET `post_content` = REPLACE(`post_content`, '" . $image . "',  '" . str_replace($ext, $type, $image) . "') WHERE `post_content` LIKE '%" . $image . "%';";
			$sql .= "UPDATE `" . $table_prefix . "postmeta` SET `meta_value` = REPLACE(`meta_value`, '" . $image . "',  '" . str_replace($ext, $type, $image) . "') WHERE `meta_value` LIKE '%" . $image . "%';";
			$count++;
        }
    }

    if($con->multi_query($sql))
        echo 'Successfully updated ' . $count . ' images';
    else
        die ('Multi query failed: ' . $con->error);

    /*
    //get all tables
    $sql = "SHOW TABLES;";
    $tables = $con->query($sql);

    if($tables->num_rows > 0 ){
        //search all tables
        while($table = $tables->fetch_assoc()){
            //get all fields
            $sql = "SELECT * FROM " . $table .";";
            $fields = $con->query($sql);

            if($fields->num_rows > 0 ){
                //search all fields
                $field = $fields->fetch_assoc_array()
                $sql = "SELECT " . $this->fieldCond($field, , ',') . " FROM " . $table . ";";

            }
        }
    }*/

    $con->close();
    unset($con);
}

if(isset($_POST['tojpg']) && !empty($_POST['image'])){
    $path = dirname(__FILE__) .'/wp-content/uploads/';
    $images = $_POST['image'];
	
	if(!isset($_POST['convertonly'])){
        findEntries($images, 'jpg');
    }
	
    foreach($images as $image){
        //if(is_file($path . $image)){
            $imgpath = pathinfo($image);
            list($width, $height, $type) = getimagesize($path . $image);
            if($type == 'jpg')
                continue;
            $img = new Imagick($path . $image);
            $img->setImageBackgroundColor('white');
            $img = $img->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $img->setCompression(Imagick::COMPRESSION_JPEG);
            $img->setCompressionQuality(97);
            $img->setImageFormat('jpg');
            $img->writeImage($path . str_replace($imgpath['extension'], 'jpg', $image));
            unset($img);
            if(isset($_POST['autodel']))
                unlink($path . $image);
        //}
    }
}
elseif(isset($_POST['topng']) && !empty($_POST['image'])){
    $path = dirname(__FILE__) .'/wp-content/uploads/';
    $images = $_POST['image'];
	
	if(!isset($_POST['convertonly'])){
        findEntries($images, 'png');
    }
	
    foreach($images as $image){
        //if(is_file($path . $image)){
            $imgpath = pathinfo($image);
            list($width, $height, $type) = getimagesize($path . $image);
            if($type == 'png')
                continue;
            $img = new Imagick($path . $image);
            $img->setCompressionQuality(97);
            $img->setImageFormat('png');
            $img->writeImage($path . str_replace($imgpath['extension'], 'png', $image));
            unset($img);

            if(isset($_POST['autodel']))
                unlink($path . $image);
        //}
    }
}
elseif(isset($_POST['delete'])){
    $path = dirname(__FILE__) .'/wp-content/uploads/';
	if(!empty($_POST['image'])){
    $images = $_POST['image'];
		foreach($images as $image){
			unlink($path . $image);
		}
	}
	
	if(!empty($_POST['file'])){
    $files = $_POST['file'];
		foreach($files as $file){
			unlink($path . $file);
		}
	}
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
	<meta name="robots" content="noindex, nofollow" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Image Fix</title>
	<style>
	body{
		font-family: arial, verdana;
		font-size: 14pt;
	}

	ul.files{
		list-style: none;
		padding-left: 0;
		margin: 5px;
	}

	ul.files li{
		position: relative;
		min-width: 200px;
		margin: 5px;
		float: left;
	}

	ul.files li::after{
		content: '';
		display: table;
		clear: both;
		zoom: 1;
	}

	li.file{
		display: inline-block;
		width: auto;
	}

	.item-check{
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		opacity: 0;
		z-index: 1;
	}

	.item{
		display: block;
		position: relative;
		min-width: 200px;
		min-height: 200px;
		margin: 15px 1%;
		box-shadow: 0 0 0 1px rgba(13,71,161 ,1);
		border-radius: 3px;
		box-sizing: border-box;
		overflow: hidden;
	}

	.item-check:checked + .item{
		box-shadow: 0 0 0 4px rgba(13,71,161 ,1);
	}

	.item-check:checked + .item::after{
		content: "✔";
		position: absolute;
		top: 0;
		left: 0;
		width: 20px;
		height: 20px;
		padding: 5px;
		color: #fff;
		background-color: rgba(13,71,161 ,1);
		border-radius: 0 0 4px 0;
	}

	.item > .title{
		display: block;
		position: absolute;
		left: 0;
		bottom: 0;
		width: 100%;
		min-height: 20px;
		color: #ffffff;
		font-weight: 600;
		background: rgba(0,0,0 ,0.7);
		text-shadow: 0px 0px 2px #333;
	}

	.title > a{
		display: block;
		position: relative;
		color: #ffffff;
		text-decoration: none;
		padding: 10px;
		z-index: 1;
	}

	.item-folder{
		background-color: rgba(21,101,192 ,0.1);
		padding: 10px;
		box-shadow: 0 0 0 1px rgba(13,71,161 ,0.4);
	}

	.item-folder::before{
		content: ' ';
		position: absolute;
		top: -10px;
		left: 0;
		width: 100%;
		height: 50px;
		background-color: rgba(13, 72, 160, 0.3);
		border-bottom: 1px solid #333;
		
	}

	.item-folder > .title{
		position: relative;
		background-color: transparent;
	}

	.item-file{
		background-color: rgba(13,71,161 ,0.7);
		width: 200px;
	}

	.item-file::before{
		content: 'file';
		position: absolute;
		top: 35px;
		left: 60px;
		width: 75px;
		height: 100px;
		background: linear-gradient(#fff, #ccc);
		border-radius: 0 15px 0 0;
		border: 1px solid #333;
	}

	.item-image{
		width: 200px;
		background-color: #eee;
		background-image: linear-gradient(45deg, #cccccc 25%, transparent 25%, transparent 75%, #cccccc 75%, #cccccc),
		linear-gradient(45deg, #cccccc 25%, transparent 25%, transparent 75%, #cccccc 75%, #cccccc);
		background-size:10px 10px;
		background-position:0 0, 5px 5px
		overflow: hidden;
	}

	.item-image > .image{
		width: 200px;
		height: 200px;
	}

	.item-image > .image img{
		min-width: 100%;
		min-height: 100%;
		object-fit: cover;
		border-radius: 3px;
	}
	
	.space{
		display: inline-block;
		margin: 0 20px;
	}
	
	#type{
		display: block;
		position: fixed;
		top: 0;
		right: -225px;
		width: 300px;
		height: 50px;
		padding: 0px 15px;
		background-color: rgba(0, 0, 0, 0.7);
		color: #fff;
		border-radius: 0 0 0 15px;
		transition: all 0.2s ease-in-out;
		z-index: 1;
		
	}
	
	#type:hover{
		right: 0;
	}
	
	#type > *{
		margin: 5px;
	}
	
	#type button{
		background: none;
		color: #333;
		padding: 5px 10px;
		background-color: #fff;
		border: 0;
		border-radius: 3px;
		font-size: 12pt;
		
	}

	#options-panel{
		position: fixed;
		left: 0;
		bottom: -100px;
		width: 100%;
		height: 50px;
		color: #fff;
		background-color: #00f;
		transition: all 0.2s ease-in-out;
		z-index: 1;
	}

	#options-panel button{
		background: none;
		color: #fff;
		padding: 5px 10px;
		border: 2px solid #fff;
		border-radius: 3px;
		font-size: 12pt;
	}

	#info{
		padding: 10px;
		float: left;
	}

	#choices{
		padding: 10px;
		float: left;
	}


	#actions{
		padding: 10px;
		float: right;
	}

	@media (max-width: 768px){
		#type{
			right: 0;
		}
		
		#options-panel{
			height: 100px;
		}
	}
	
	@media (max-width: 430px){
		#type{
			width: 100%;
			border-radius: 0;
			padding: 0;
		}
		
		#options-panel{
			height: 150px;
		}
	}
	
	</style>
</head>
<body>
	<form id="files-form" action="" method="POST">

	<?php listFolderFiles('wp-content/uploads'); ?>
	
	<div id="type">
	<?php if(isset($_POST['jpg'])): ?>
		<button type="submit" name="jpg">.JPG Only</button>
		<button type="submit" name="png">.PNG Only</button>
		<button type="submit" name="all">All files</button>
	<?php elseif(isset($_POST['png'])): ?>
		<button type="submit" name="png">.PNG Only</button>
		<button type="submit" name="jpg">.JPG Only</button>
		<button type="submit" name="all">All files</button>
	<?php else: ?>
		<button type="submit" name="all">All files</button>
		<button type="submit" name="jpg">.JPG Only</button>
		<button type="submit" name="png">.PNG Only</button>	
	<?php endif ?>
	</div>
	<div id="options-panel">
		<div id="info"></div>
		<div id="choices">
			<input id="autodel" type="checkbox" name="autodel" />
			<label for="autodel">Delete originals</label>
			<input id="convertonly" type="checkbox" name="convertonly" checked />
			<label for="convertonly">Convert Only</label>
		</div>
		<div id="actions">
			<button type="submit" name="tojpg">To .JPG</button>
			<button type="submit" name="topng">To .PNG</button>
			<button type="submit" name="delete">Delete</button>
			<button type="reset" name="reset" onclick="showOptions();" >Uncheck All</button>
		</div>
	</div>
	</form>

	<script>

	function showOptions(){
		var checkboxes = document.getElementsByClassName('item-check');
		var items_checked = Array.prototype.slice.call(checkboxes).some(x => x.checked);
		var options = document.getElementById('options-panel');
		
		document.getElementById('info').innerHTML = 'Selected: ' + selected(checkboxes);
		
		if (items_checked){
			options.style.bottom = '0';
		}
		else {
			options.style.bottom = '-150px';
		}

	}

	function selected(items = []){
		var total = 0;
		for(var i = 0; i < items.length; i++){
			if(items[i].checked)
				total++;
		}
		return total;
	}

	function selectChildren(id){
		var input = document.getElementById(id);
		var parent = input.parentNode;
		if(input.checked && parent.getAttribute('class') == 'folder'){
			var children = parent.getElementsByClassName('item-check');
			for(var i = 0; i < children.length; i++){
				children[i].checked = true;
			}
		}
	}

	showOptions();

	</script>
</body>
</html>