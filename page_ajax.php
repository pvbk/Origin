<?php
/**
 * Редактирование страниц
 */
require_once( dirname(__file__).'/lib/dir.php' );
require_once( SITE_CONFIG );
require_once( CMS_CONFIG );
require_once( DIR_LIB.'admin_data.php' );
require_once( DIR_LIB.'admin_funcs.php' );
require_once( DIR_LIB.'initdb.php' );


$response = 'Error';
$page_name_default = 'Новая страница';
$page_url_default = 'page-num-';
$sect_name_default = 'Новый раздел';
$rec_name_default = 'Новая запись';
$ratio = 1.32;


//// вывести форму новой страницы для редактирования
if(isset($_GET['newPage'])) {
  $ind = intval($_GET['newPage']);
  miniDB::dbInit();
  $new_ind = miniDB::dbFetchCell('SELECT MAX(ind) FROM '.PFX.'pages;');  $new_ind ++;
  $parent_name = miniDB::dbFetchCell('SELECT name FROM '.PFX.'pages WHERE ind='.$ind.';');
  $resurs = miniDB::dbQuery('SELECT name FROM '.PFX.'pages WHERE prnt_ind='.$ind.' ORDER BY pos;');
  $num_rows = miniDB::dbNumRows($resurs); $sum = '<option value="0">-- Первой --</option>'.CRNL;
  for($i=1; $i<$num_rows; $i++) {
    $data = miniDB::dbAssoc($resurs);
    $sum .= '<option value='.$i.'>'.$data['name'].'</option>'.CRNL;
  }
  if($num_rows > 0) {
    $data = miniDB::dbAssoc($resurs);
    $sum .= '<option value='.$num_rows.' selected="selected">'.$data['name'].'</option>'.CRNL;
  }
  miniDB::dbClose();
  // вывод формы
  $result['page_frm'] = 'new';  $result['page_ind'] = $new_ind;
  $result['prnt_ind'] = $ind;  $result['prnt_name'] = $parent_name;
  $result['page_list'] = $sum;  $result['amnt_pos'] = $num_rows;
  $result['name'] = $page_name_default;  $result['href'] = $page_url_default.$new_ind;
  $result['title'] = $result['meta_kwords'] = $result['meta_dscrip'] = '';
  $tpl_content = file_get_contents(DIR_CORE.'inc'.DSR.'tpl'.DSR.'page.htm');
  $response = do_replaces($result, $tpl_content).CRNL;
}

//// вывести форму существующей страницы для редактирования
elseif(isset($_GET['editPage'])) {
  $ind = intval($_GET['editPage']);
  miniDB::dbInit();
  $resurs = miniDB::dbQuery('SELECT prnt_ind,pos,name,href,title,meta_kwords,meta_dscrip FROM '.PFX.'pages WHERE ind='.$ind.';');
  $result = miniDB::dbAssoc($resurs); $pos = $result['pos'] - 1;
  if($ind == 1) {
    $parent_name = '&#9472;';
  } else {
    $parent_name = miniDB::dbFetchCell('SELECT name FROM '.PFX.'pages WHERE ind='.$result['prnt_ind'].';');
  }
  $resurs1 = miniDB::dbQuery('SELECT ind,name FROM '.PFX.'pages WHERE prnt_ind='.$result['prnt_ind'].' ORDER BY pos;');
  $num_rows = miniDB::dbNumRows($resurs1); $sum = '<option value="0">-- Первой --</option>'.CRNL;
  for($i=1; $i<=$num_rows; $i++) {
    $data = miniDB::dbAssoc($resurs1);
    if($data['ind'] == $ind) continue;
    $sltd = ($i == $pos)? ' selected="selected"' : '';
    $sum .= '<option value='.$i.$sltd.'>'.$data['name'].'</option>'.CRNL;
  }
  miniDB::dbClose();
  // вывод формы
  $result['page_frm'] = 'edit';  $result['page_ind'] = $ind;  $result['prnt_name'] = $parent_name;
  $result['page_list'] = $sum;  $result['amnt_pos'] = $num_rows;
  $tpl_content = file_get_contents(DIR_CORE.'inc'.DSR.'tpl'.DSR.'page.htm');
  $response = do_replaces($result, $tpl_content).CRNL;
}

//// удалить страницу
elseif(isset($_GET['delPage'])) {
  $ind = intval($_GET['delPage']);  $err_msg = '';
  miniDB::dbInit();
  if($ind < 1) $err_msg = 'Error: Removing main page impossible';
  else {
    // определить индекс родительской страницы
    $resurs1 = miniDB::dbQuery('SELECT prnt_ind, pos, att_pages, att_sects, att_ctlgs FROM '.PFX.'pages WHERE ind='.$ind.';');
    $result = miniDB::dbAssoc($resurs1);
    if($result['att_pages'] == 'y') $err_msg = 'Error: The Page has a child pages. Removing impossible';
    elseif($result['att_sects'] == 'y') $err_msg = 'Error: The Page has attached sections. Removing impossible';
    elseif($result['att_ctlgs'] == 'y') $err_msg = 'Error: The Page has attached catalogs. Removing impossible';
    else {
      $prnt_ind = $result['prnt_ind']; $rem_pos = $result['pos'];
      // определить количество позиций
      $max_pos = miniDB::dbFetchCell('SELECT MAX(pos) FROM '.PFX.'pages WHERE prnt_ind='.$prnt_ind.';');
      // удалить страницу и сомкнуть оставшиеся страницы
      if(($max_pos > 0) and ($rem_pos <= $max_pos)) {
        miniDB::dbQuery('DELETE FROM '.PFX.'pages WHERE prnt_ind='.$prnt_ind.' AND pos='.$rem_pos.';');
        for($i=$rem_pos; $i<$max_pos; $i++) {
          miniDB::dbQuery('UPDATE '.PFX.'pages SET pos='.$i.' WHERE prnt_ind='.$prnt_ind.' AND pos='.($i+1).';');
        }
      }
      // удалить в отметку о прикрепленной странице
      if($max_pos == 1) {
        miniDB::dbQuery('UPDATE '.PFX.'pages SET att_pages=\'x\' WHERE ind='.$prnt_ind.';');
      }
    }
  }
  if(!empty($err_msg)) $err_msg = '<p class="page-err">'.$err_msg.'</p>'.CRNL;
  $page_list = ''; $att = 'sec';
  show_page_list(0);
  $response = $page_list.$err_msg;
  miniDB::dbClose();
}

//// сохранить новую страницу
elseif(isset($_POST['page-frm']) and $_POST['page-frm'] == 'new') {
  $prnt_ind  = (isset($_POST['prnt-ind']))? intval($_POST['prnt-ind']) : 0;
  $page_name = (isset($_POST['page-name']))? parser(cut_str($_POST['page-name'], ADMSTRSIZEMAX)) : '';
  if(strlen($page_name) < 2) $page_name = $page_name_default;
  $page_url  = (isset($_POST['page-url']))? parser(cut_str($_POST['page-url'], 60)) : '';
  if(!preg_match('/^([_0-9a-z-]*)$/i', $page_url)) $page_url = $page_url_default;
  $amnt_pos  = (isset($_POST['amnt-pos']))? intval($_POST['amnt-pos']) : 0;
  $pos_after = (isset($_POST['page-pos']))? intval($_POST['page-pos']) : 0;
  $title  = (isset($_POST['title']))? parser(cut_str($_POST['title'])) : '';
  $kwords = (isset($_POST['meta-kwords']))? parser(cut_str($_POST['meta-kwords'])) : '';
  $dscrip = (isset($_POST['meta-dscrip']))? parser(cut_str($_POST['meta-dscrip'])) : '';
  extDB::dbInit();
  if(empty($amnt_pos)) {
    // отметить, что родительская страница имеет присоединенную
    miniDB::dbQuery('UPDATE '.PFX.'pages SET att_pages=\'y\' WHERE ind='.$prnt_ind.';');
  }
  // Переместить страницу на (pos_total - pos_number) позиций вниз
  for($i=$amnt_pos; $i>$pos_after; $i--) {
    miniDB::dbQuery('UPDATE '.PFX.'pages SET pos='.($i + 1).' WHERE prnt_ind='.$prnt_ind.' AND pos='.$i.';');
  }
  // вставить новую страницу
//extDB::dbQuery('INSERT INTO '.PFX.'pages (prnt_ind,pos,name,href,title,meta_kwords,meta_dscrip) VALUES ('.$prnt_ind.','.($pos_after+1).',?,?,?,?,?);', $page_name,$page_url,$title,$kwords,$dscrip);
  miniDB::dbQuery('INSERT INTO '.PFX.'pages (prnt_ind,pos,name,href,title,meta_kwords,meta_dscrip) VALUES ('.$prnt_ind.','.($pos_after+1).',\''.$page_name.'\',\''.$page_url.'\',\''.$title.'\',\''.$kwords.'\',\''.$dscrip.'\');');
  $page_list = ''; $att = 'sec';
  show_page_list(0);
  $response = $page_list;
  miniDB::dbClose();
}

//// сохранить сущестующую страницу
elseif(isset($_POST['page-frm']) and $_POST['page-frm'] == 'edit') {
  $page_ind  = (isset($_POST['page-ind']))? intval($_POST['page-ind']) : 0;
  $prnt_ind  = (isset($_POST['prnt-ind']))? intval($_POST['prnt-ind']) : 0;
  $page_name = (isset($_POST['page-name']))? parser(cut_str($_POST['page-name'], ADMSTRSIZEMAX)) : '';
  if(strlen($page_name) < 2) $page_name = $page_name_default;
  $page_url  = (isset($_POST['page-url']))? parser(cut_str($_POST['page-url'], 60)) : '';
  if(!preg_match('/^([_0-9a-z-]*)$/i', $page_url)) $page_url = $page_url_default;
  $amnt_pos  = (isset($_POST['amnt-pos']))? intval($_POST['amnt-pos']) : 0;
  $pos_after = (isset($_POST['page-pos']))? intval($_POST['page-pos']) : 0;
  $title  = (isset($_POST['title']))? parser(cut_str($_POST['title'])) : '';
  $kwords = (isset($_POST['meta-kwords']))? parser(cut_str($_POST['meta-kwords'])) : '';
  $dscrip = (isset($_POST['meta-dscrip']))? parser(cut_str($_POST['meta-dscrip'])) : '';
  extDB::dbInit();
  // сохранить данные формы в БД
  extDB::extQuery('UPDATE '.PFX.'pages SET name=?, href=?, title=?, meta_kwords=?, meta_dscrip=? WHERE ind='.$page_ind.';', $page_name, $page_url, $title, $kwords, $dscrip);
  // определить старую и новую позиции страниц
  $resurs = miniDB::dbQuery('SELECT prnt_ind, pos FROM '.PFX.'pages WHERE ind='.$page_ind.';');
  $data = miniDB::dbAssoc($resurs);
  $old_pos = $data['pos']; $new_pos = $pos_after + 1; $prnt_ind_db = $data['prnt_ind'];
  if($new_pos < $old_pos) {
    // переместить страницу на новую позицию вверх
    miniDB::dbQuery('UPDATE '.PFX.'pages SET pos=999998 WHERE prnt_ind='.$prnt_ind_db.' AND pos='.$old_pos.';');
    for($i=$old_pos; $i>$new_pos; $i--) {
      miniDB::dbQuery('UPDATE '.PFX.'pages SET pos='.$i.' WHERE prnt_ind='.$prnt_ind_db.' AND pos='.($i-1).';');
    }
    miniDB::dbQuery('UPDATE '.PFX.'pages SET pos='.$new_pos.' WHERE prnt_ind='.$prnt_ind_db.' AND pos=999998;');
  }
  elseif($new_pos > $old_pos) {
    // переместить страницу на новую позицию вниз
    miniDB::dbQuery('UPDATE '.PFX.'pages SET pos=999999 WHERE prnt_ind='.$prnt_ind_db.' AND pos='.$old_pos.';');
    for($i=$old_pos; $i<$pos_after; $i++) {
      miniDB::dbQuery('UPDATE '.PFX.'pages SET pos='.$i.' WHERE prnt_ind='.$prnt_ind_db.' AND pos='.($i+1).';');
    }
    miniDB::dbQuery('UPDATE '.PFX.'pages SET pos='.$pos_after.' WHERE prnt_ind='.$prnt_ind_db.' AND pos=999999;');
  }
  $page_list = ''; $att = 'sec';
  show_page_list(0);
  $response = $page_list;
  miniDB::dbClose();
}

//// показать список разделов
elseif(isset($_GET['sectList'])) {
  $page_ind  = intval($_GET['sectList']);
  miniDB::dbInit();
  $resurs = miniDB::dbQuery('SELECT ind,name FROM '.PFX.'sections WHERE page_ind='.$page_ind.' ORDER BY pos;');
  $num_rows = miniDB::dbNumRows($resurs);
  if(empty($num_rows)) {
    $sum = '<li> Разделов нет</li>'.CRNL;
  }
  else {
    $sum = '';
    for($i=1; $i<=$num_rows; $i++) {
      $data = miniDB::dbAssoc($resurs);
      $sum .= '<li>'.$data['name'].'<span class="m-btn">'.EDTSECT.$data['ind'].BTNEND.DELSECT.$page_ind.','.$data['ind'].BTNEND.'</span></li>'.CRNL;
    }
  }
  miniDB::dbClose();
  $result['pg_ind'] = $page_ind;
  $result['sect_list'] = $sum;
  $tpl_content = file_get_contents(DIR_CORE.'inc'.DSR.'tpl'.DSR.'sect-list.htm');
  $response = do_replaces($result, $tpl_content).CRNL;
}

//// удалить раздел
elseif(isset($_GET['delSect'])) {
  $sect_ind = intval($_GET['delSect']);
  miniDB::dbInit();
  // определить индекс страницы и номер позиции
  $rss = miniDB::dbQuery('SELECT page_ind, pos FROM '.PFX.'sections WHERE ind='.$sect_ind.';');
  $rlt = miniDB::dbAssoc($rss); $rem_pos = $rlt['pos']; $page_ind = $rlt['page_ind'];
  // определить максимальную позицию
  $max_pos = miniDB::dbFetchCell('SELECT MAX(pos) FROM '.PFX.'sections WHERE page_ind='.$page_ind.';');
  // удалить раздел и сомкнуть оставшиеся разделы
  miniDB::dbQuery('DELETE FROM '.PFX.'sections WHERE ind='.$sect_ind.';');
  for($i=$rem_pos; $i<$max_pos; $i++) {
    miniDB::dbQuery('UPDATE '.PFX.'sections SET pos='.$i.' WHERE page_ind='.$page_ind.' AND pos='.($i+1).';');
  }
  // удалить изображения
  $rs = miniDB::dbQuery('SELECT src FROM '.PFX.'images WHERE ext_ind='.$sect_ind.' AND ext_typ=\'sec\';');
  $nr = miniDB::dbNumRows($rs);
  for($k=0; $k<$nr; $k++) {
    $dt = miniDB::dbAssoc($rs);
    unlink(DIR_IMG.$dt['src']);  unlink(DIR_ADM.'images'.DSR.'a'.$dt['src']);
  }
  miniDB::dbQuery('DELETE FROM '.PFX.'images WHERE ext_ind='.$sect_ind.' AND ext_typ=\'sec\';');
  // показать обновленный список разделов
  if($max_pos == 1) {
    // удалить в отметку о прикрепленном разделе
    miniDB::dbQuery('UPDATE '.PFX.'pages SET att_sects=\'x\' WHERE ind='.$page_ind.';');
    $sum = '<li> Разделов нет</li>'.CRNL;
  }
  else {
    $sum = '';
    $rs = miniDB::dbQuery('SELECT ind,name FROM '.PFX.'sections WHERE page_ind='.$page_ind.' ORDER BY pos;');
    $nr = miniDB::dbNumRows($rs);
    for($i=1; $i<=$nr; $i++) {
      $dt = miniDB::dbAssoc($rs);
      $sum .= '<li>'.$dt['name'].'<span class="m-btn">'.EDTSECT.$dt['ind'].BTNEND.DELSECT.$page_ind.','.$dt['ind'].BTNEND.'</span></li>'.CRNL;
    }
  }
  $response = $sum;
  miniDB::dbClose();
}

//// вывести форму нового раздела для редактирования
elseif(isset($_GET['newSect'])) {
  $ind = intval($_GET['newSect']);    // индекс страницы
  miniDB::dbInit();
  $page_name = miniDB::dbFetchCell('SELECT name FROM '.PFX.'pages WHERE ind='.$ind.';');
  //$sect_ind = miniDB::dbFetchCell('SELECT MAX(ind) FROM '.PFX.'sections;');  $sect_ind ++;
  $sect_ind = '524288';
  $resurs = miniDB::dbQuery('SELECT ind,name FROM '.PFX.'sections WHERE page_ind='.$ind.' ORDER BY pos;');
  $num_rows = miniDB::dbNumRows($resurs); $sum = '<option value="0">-- Первым --</option>'.CRNL;
  for($i=1; $i<$num_rows; $i++) {
    $data = miniDB::dbAssoc($resurs);
    $sum .= '<option value='.$i.'>'.$data['name'].'</option>'.CRNL;
  }
  if($num_rows > 0) {
    $data = miniDB::dbAssoc($resurs);
    $sum .= '<option value='.$num_rows.' selected="selected">'.$data['name'].'</option>'.CRNL;
  }
  miniDB::dbClose();
  // вывод формы
  $result['sect_form'] = 'new';  $result['page_ind'] = $ind;  $result['page_name'] = $page_name;
  $result['sect_ind'] = $sect_ind;  $result['amnt_pos'] = $num_rows;
  $result['sect_list'] = $sum;  $result['name'] = $sect_name_default;
  $result['caption'] = $result['info'] = $result['imgs'] = '';
  $tpl_content = file_get_contents(DIR_CORE.'inc'.DSR.'tpl'.DSR.'section.htm');
  $response = WSWIMGFORM.do_replaces($result, $tpl_content).CRNL;
}

//// вывести форму существующего раздела для редактирования
elseif(isset($_GET['editSect'])) {
  $sect_ind = intval($_GET['editSect']);    // индекс раздела
  miniDB::dbInit();
  $resurs = miniDB::dbQuery('SELECT page_ind,pos,name,caption,info FROM '.PFX.'sections WHERE ind='.$sect_ind.';');
  $result = miniDB::dbAssoc($resurs);  $page_ind = $result['page_ind'];  $pos = $result['pos'] - 1;
  $page_name = miniDB::dbFetchCell('SELECT name FROM '.PFX.'pages WHERE ind='.$page_ind.';');
  $resurs1 = miniDB::dbQuery('SELECT ind,name FROM '.PFX.'sections WHERE page_ind='.$page_ind.' ORDER BY pos;');
  $num_rows = miniDB::dbNumRows($resurs1); $sum = '<option value="0">-- Первым --</option>'.CRNL;
  for($i=1; $i<=$num_rows; $i++) {
    $data = miniDB::dbAssoc($resurs1);
    if($data['ind'] == $sect_ind) continue;
    $sltd = ($i == $pos)? ' selected="selected"' : '';
    $sum .= '<option value='.$i.$sltd.'>'.$data['name'].'</option>'.CRNL;
  }
  // извлечь изображения
  $rss = miniDB::dbQuery('SELECT pos,src,alt FROM '.PFX.'images WHERE ext_ind='.$sect_ind.' AND ext_typ=\'sec\' ORDER BY pos;');
  $nmr = miniDB::dbNumRows($rss);  $imgs = '';
  for($k=0; $k<$nmr; $k++) {
    $rlt = miniDB::dbAssoc($rss);
    $src = '<img src="images/a'.$rlt['src'].'" alt="'.$rlt['alt'].'">';
    $imgs .= '<p class="imgs-prgrf" id="imgpos'.$rlt['pos'].'">'.$src.'<button class="cms-button__small imgs-button__rem" onclick="return imgRemove('.$sect_ind.','.$rlt['pos'].',\'sec\')">Удалить</button>&nbsp;<ins id="delayRem'.$rlt['pos'].'"></ins></p>'.CRNL;
  }
  miniDB::dbClose();
  // вывод формы
  $result['sect_form'] = 'edit';  $result['page_name'] = $page_name;
  $result['sect_ind'] = $sect_ind;  $result['amnt_pos'] = $num_rows;
  $result['sect_list'] = $sum;  $result['imgs'] = $imgs;
  $tpl_content = file_get_contents(DIR_CORE.'inc'.DSR.'tpl'.DSR.'section.htm');
  $response = WSWIMGFORM.do_replaces($result, $tpl_content).CRNL;
}

//// сохранить раздеел
elseif(isset($_POST['sect-form'])) {
  $page_ind = (isset($_POST['page-ind']))? intval($_POST['page-ind']) : 0;
  $sect_ind = (isset($_POST['sect-ind']))? intval($_POST['sect-ind']) : 0;
  $sect_name = (isset($_POST['sect-name']))? cut_str($_POST['sect-name'], ADMSTRSIZEMAX) : '';
  if(strlen($sect_name) < 2) $sect_name = $sect_name_default;
  $amnt_pos  = (isset($_POST['amnt-pos']))? intval($_POST['amnt-pos']) : 0;
  $pos_after = (isset($_POST['sect-pos']))? intval($_POST['sect-pos']) : 0;
  $caption = (isset($_POST['sect-cap']))? cut_str($_POST['sect-cap'], ADMSTRSIZEMAX) : '';
  $info    = (isset($_POST['text-info']))? limit_text($_POST['text-info']) : '';
  extDB::dbInit();
  if($_POST['sect-form'] == 'new') {
    // создать новый раздел
    miniDB::dbQuery('UPDATE '.PFX.'pages SET att_sects=\'y\' WHERE ind='.$page_ind.';');
    // Раздвинуть разделы вниз начиная с позиции $pos_after + 1
    for($i=$amnt_pos; $i>$pos_after; $i--) {
      miniDB::dbQuery('UPDATE '.PFX.'sections SET pos='.($i+1).' WHERE page_ind='.$page_ind.' AND pos='.$i.';');
    }
    // вставить новый раздел
  //extDB::dbQuery('INSERT INTO '.PFX.'sections (page_ind,pos,name,caption,info) VALUES ('.$page_ind.','.($pos_after+1).',?,?,?);', $sect_name,$caption,$info);
    miniDB::dbQuery('INSERT INTO '.PFX.'sections (page_ind,pos,name,caption,info) VALUES ('.$page_ind.','.($pos_after+1).',\''.$sect_name.'\',\''.$caption.'\',\''.$info.'\');');
    // определить новый номер раздела
    $sect_ind = miniDB::dbFetchCell('SELECT MAX(ind) FROM '.PFX.'sections WHERE page_ind='.$page_ind.';');
  }
  else {    // $_POST['sect-form'] == 'edit'
    // сохранить данные формы в БД
    extDB::extQuery('UPDATE '.PFX.'sections SET name=?, caption=?, info=? WHERE ind='.$sect_ind.';', $sect_name, $caption, $info);
    // определить старую и новую позиции страниц
    $resurs = miniDB::dbQuery('SELECT page_ind, pos FROM '.PFX.'sections WHERE ind='.$sect_ind.';');
    $data = miniDB::dbAssoc($resurs);
    $old_pos = $data['pos']; $new_pos = $pos_after + 1; $page_ind_db = $data['page_ind'];
    if($new_pos < $old_pos) {
      // переместить раздел на новую позицию вверх
      miniDB::dbQuery('UPDATE '.PFX.'sections SET pos=999998 WHERE page_ind='.$page_ind_db.' AND pos='.$old_pos.';');
      for($i=$old_pos; $i>$new_pos; $i--) {
        miniDB::dbQuery('UPDATE '.PFX.'sections SET pos='.$i.' WHERE page_ind='.$page_ind_db.' AND pos='.($i-1).';');
      }
      miniDB::dbQuery('UPDATE '.PFX.'sections SET pos='.$new_pos.' WHERE page_ind='.$page_ind_db.' AND pos=999998;');
    }
    elseif($new_pos > $old_pos) {
      // переместить раздел на новую позицию вниз
      miniDB::dbQuery('UPDATE '.PFX.'sections SET pos=999999 WHERE page_ind='.$page_ind_db.' AND pos='.$old_pos.';');
      for($i=$old_pos; $i<$pos_after; $i++) {
        miniDB::dbQuery('UPDATE '.PFX.'sections SET pos='.$i.' WHERE page_ind='.$page_ind_db.' AND pos='.($i+1).';');
      }
      miniDB::dbQuery('UPDATE '.PFX.'sections SET pos='.$pos_after.' WHERE page_ind='.$page_ind_db.' AND pos=999999;');
    }
  }
  // определить позицию последнего изображения
  $max_pos = miniDB::dbFetchCell('SELECT MAX(pos) FROM '.PFX.'images WHERE ext_ind='.$sect_ind.' AND ext_typ=\'sec\';');
  $max_pos ++;  $msg_sum = '';
  // закачать изображения на сервер
  if($_FILES) {
    foreach($_FILES[UPLOADFILEID]['error'] as $key => $error) {
      $msg = '';
      if ($error == UPLOAD_ERR_OK) {
        $file_ext = $_FILES[UPLOADFILEID]['type'][$key];
        if(!isset($imgtype[$file_ext])) {
          $msg = '<p class="fuplerr">Error. File is not a file of the image</p>'.CRNL;
        }
        elseif($_FILES[UPLOADFILEID]['size'][$key] > ADMFILESIZEMAX) {
          $msg = '<p class="fuplerr">Error. Size of the file too great</p>'.CRNL;
        }
        else {    // сохранить изображения
          $extrn = $imgtype[$file_ext];
          $filename = 's'.$sect_ind.getrandname().'.'.$extrn;
          $img_src = $_FILES[UPLOADFILEID]['tmp_name'][$key];
          $img_dst = DIR_IMG.$filename;
          $img_adm = DIR_ADM.'images'.DSR.'a'.$filename;
          $img_alt = (isset($_POST['imgAlt'][$key]))? parser(cut_str($_POST['imgAlt'][$key])) : '';
          $img_ref = (isset($_POST['imgRef'][$key]))? parser(cut_str($_POST['imgRef'][$key])) : '';
          $width_dst = (isset($_POST['imgWidth'][$key]))? intval($_POST['imgWidth'][$key]) : 1;
          if($width_dst > IMGWIDTHMAX) $width_dst = IMGWIDTHMAX;  elseif($width_dst < 1) $width_dst = 1;
          $height_dst = (int)floor($ratio*$width_dst);  $height_adm = (int)floor($ratio*IMGWIDTHADMIN);
          $errdst = resizeimage($extrn, $img_src, $img_dst, $width_dst, $height_dst);
          $erradm = resizeimage($extrn, $img_src, $img_adm, IMGWIDTHADMIN, $height_adm);
          if(!$errdst or !$erradm) $msg = '<p class="fuplerr">Error of transformation '.strtoupper($extrn).' - file</p>'.CRNL;
          // удалить временный файл
          unlink($img_src);
        }
        if(empty($msg)) {
          // записать данные о изображении в БД
          miniDB::dbQuery('INSERT INTO '.PFX.'images (ext_ind,ext_typ,pos,src,alt,href) VALUES ('.$sect_ind.',\'sec\','.($max_pos + $key).',\''.$filename.'\',\''.$img_alt.'\',\''.$img_ref.'\');');
        }
      }
      $msg_sum .= $msg;
    }
  }
  // показать изображения
  $rss = miniDB::dbQuery('SELECT pos,src,alt FROM '.PFX.'images WHERE ext_ind='.$sect_ind.' AND ext_typ=\'sec\' ORDER BY pos;');
  $nmr = miniDB::dbNumRows($rss);  $imgs = '';
  for($k=0; $k<$nmr; $k++) {
    $rlt = miniDB::dbAssoc($rss);
    $src = '<img src="images/a'.$rlt['src'].'" alt="'.$rlt['alt'].'">';
    $imgs .= '<p class="imgs-prgrf" id="imgpos'.$rlt['pos'].'">'.$src.'<button class="cms-button__small imgs-button__rem" onclick="return imgRemove('.$sect_ind.','.$rlt['pos'].',\'sec\')">Удалить</button>&nbsp;<ins id="delayRem'.$rlt['pos'].'"></ins></p>'.CRNL;
  }
  $img_sum = $imgs.$msg_sum;
  // обновить список разделов
  $resurs = miniDB::dbQuery('SELECT ind,name FROM '.PFX.'sections WHERE page_ind='.$page_ind.' ORDER BY pos;');
  $num_rows = miniDB::dbNumRows($resurs);
  $sum = '';
  for($i=1; $i<=$num_rows; $i++) {
    $data = miniDB::dbAssoc($resurs);
    $sum .= '<li>'.$data['name'].'<span class="m-btn">'.EDTSECT.$data['ind'].BTNEND.DELSECT.$page_ind.','.$data['ind'].BTNEND.'</span></li>'.CRNL;
  }
  $response = $img_sum.'^'.$sum.'^';
  miniDB::dbClose();
}

//// показать каталог = список записей (records)
elseif(isset($_GET['recList'])) {
  $page_ind  = intval($_GET['recList']);
  miniDB::dbInit();
  $rec_count = miniDB::dbFetchCell('SELECT COUNT(ind) FROM '.PFX.'records WHERE page_ind='.$page_ind.';');
  if(empty($rec_count)) {
    $sum = '<li> Записей нет</li>'.CRNL;
    $swircher = '';
  }
  else {
    // номер листа
    $sheet_num = (isset($_GET['shtNum']))? intval($_GET['shtNum']) : 1;
    // параметр сортировки
    $psort = (isset($_GET['parSort']))? $_GET['parSort'] : '~';
    $psort = (preg_match('/(^[A-Z]|[А-Я]|@|~)$/', $psort))? $psort : '~';
    switch($psort) {
      case '~': $qsort = ''; $collation = ' updated DESC'; break;
      case '@': $qsort = ''; $collation = ' name'; break;
      default:  $qsort = ' AND name LIKE \''.$psort.'%\''; $collation = ' name';
    }
    // полное число записей
    $rec_full = miniDB::dbFetchCell('SELECT COUNT(ind) FROM '.PFX.'records WHERE page_ind='.$page_ind.$qsort.';');
    $rec_start = ($sheet_num-1) * ADMRECONPAGE;
    if($rec_start > $rec_full) {
      $rec_start=0;  $sheet_num=1;
    }
    // формирование списка записей каталога
    $resurs = miniDB::dbQuery('SELECT ind,name,vh FROM '.PFX.'records WHERE page_ind='.$page_ind.$qsort.' ORDER BY'.$collation.' LIMIT '.$rec_start.','.ADMRECONPAGE.';');
    $num_rows = miniDB::dbNumRows($resurs);  $sum = '';
    for($i=1; $i<=$num_rows; $i++) {
      $data = miniDB::dbAssoc($resurs);
      $ind = $data['ind']; $oi = ($i < 10)? '0'.$i : ''.$i;
      $vh = ($data['vh'] == 'v') ? VSBLREC : HDDNREC;
      $sum .= '<li><em class="r-num">'.$oi.'</em>'.$data['name'].' <span class="m-btn">'.$vh.EDTREC.$page_ind.','.$ind.BTNEND.DELREC.$page_ind.','.$ind.BTNEND.'</span></li>'.CRNL;
    }
    $swircher = show_switcher($sheet_num, $rec_full);
  }
  miniDB::dbClose();
  $result['pg_ind'] = $page_ind;  $result['rec_list'] = $sum;  $result['pg_switcher'] = $swircher;
  $tpl_content = file_get_contents(DIR_CORE.'inc'.DSR.'tpl'.DSR.'rec-list.htm');
  $response = do_replaces($result, $tpl_content).CRNL;
}

//// удалить запись
elseif(isset($_GET['delRec'])) {
  $rec_ind = intval($_GET['delRec']);
  miniDB::dbInit();
  // определить индекс страницы
  $page_ind = miniDB::dbFetchCell('SELECT page_ind FROM '.PFX.'records WHERE ind='.$rec_ind.';');
  // определить количество записей
  $amnt_rec = miniDB::dbFetchCell('SELECT COUNT(ind) FROM '.PFX.'records WHERE page_ind='.$page_ind.';');
  // удалить запись
  miniDB::dbQuery('DELETE FROM '.PFX.'records WHERE ind='.$rec_ind.';');
  // удалить изображения
  $rs = miniDB::dbQuery('SELECT src FROM '.PFX.'images WHERE ext_ind='.$rec_ind.' AND ext_typ=\'rec\';');
  $nr = miniDB::dbNumRows($rs);
  for($k=0; $k<$nr; $k++) {
    $dt = miniDB::dbAssoc($rs);
    unlink(DIR_IMG.$dt['src']); unlink(DIR_IMG.'small'.DSR.$dt['src']); unlink(DIR_ADM.'images'.DSR.'a'.$dt['src']);
  }
  miniDB::dbQuery('DELETE FROM '.PFX.'images WHERE ext_ind='.$rec_ind.' AND ext_typ=\'rec\';');
  // показать обновленный каталог
  if($amnt_rec == 1) {
    // удалить в отметку о прикрепленном каталоге
    miniDB::dbQuery('UPDATE '.PFX.'pages SET att_ctlgs=\'x\' WHERE ind='.$page_ind.';');
    $sum = '<li> Записей нет</li>'.CRNL;
  }
  else {
    // номер листа
    $sheet_num = (isset($_GET['shtNum']))? intval($_GET['shtNum']) : 1;
    // параметр сортировки
    $psort = (isset($_GET['parSort']))? $_GET['parSort'] : '~';
    $psort = (preg_match('/(^[A-Z]|[А-Я]|@|~)$/', $psort))? $psort : '~';
    switch($psort) {
      case '~': $qsort = ''; $collation = ' updated DESC'; break;
      case '@': $qsort = ''; $collation = ' name'; break;
      default:  $qsort = ' AND name LIKE \''.$psort.'%\''; $collation = ' name';
    }
    // полное число записей (с учетом сортировки по алфавиту)
    $rec_full = miniDB::dbFetchCell('SELECT COUNT(ind) FROM '.PFX.'records WHERE page_ind='.$page_ind.$qsort.';');
    $rec_start = ($sheet_num-1) * ADMRECONPAGE;
    if($rec_start > $rec_full) {
      $rec_start=0;  $sheet_num=1;
    }
    // формирование списка записей каталога
    $resurs = miniDB::dbQuery('SELECT ind,name,vh FROM '.PFX.'records WHERE page_ind='.$page_ind.$qsort.' ORDER BY'.$collation.' LIMIT '.$rec_start.','.ADMRECONPAGE.';');
    $num_rows = miniDB::dbNumRows($resurs);  $sum = '';
    for($i=1; $i<=$num_rows; $i++) {
      $data = miniDB::dbAssoc($resurs);
      $ind = $data['ind']; $oi = ($i < 10)? '0'.$i : ''.$i;
      $vh = ($data['vh'] == 'v') ? VSBLREC : HDDNREC;
      $sum .= '<li><em class="r-num">'.$oi.'</em>'.$data['name'].' <span class="m-btn">'.$vh.EDTREC.$page_ind.','.$ind.BTNEND.DELREC.$page_ind.','.$ind.BTNEND.'</span></li>'.CRNL;
    }
  }
  $response = $sum.'^'.show_switcher($sheet_num, $rec_full);
  miniDB::dbClose();
}

//// вывести форму записи для редактирования
elseif(isset($_GET['editRec'])) {
  $ind = (isset($_GET['pageInd']))? intval($_GET['pageInd']) : 0;    // индекс страницы
  miniDB::dbInit();
  $page_name = miniDB::dbFetchCell('SELECT name FROM '.PFX.'pages WHERE ind='.$ind.';');
  // показать списки
  if(ISFIRM) { $result['ISFIRM'] = ''; } else { $result['ISFIRM'] = ''; }
  if(ISAUTHOR) { $result['ISAUTHOR'] = ''; } else { $result['ISAUTHOR'] = ''; }
  if(ISSHOP) { $result['ISSHOP'] = ''; } else { $result['ISSHOP'] = ''; }
  if(ISPLACE) { $result['ISPLACE'] = ''; } else { $result['ISPLACE'] = ''; }
  if(ISCOUNTRY) { $result['ISCOUNTRY'] = ''; } else { $result['ISCOUNTRY'] = ''; }
  if(ISREGION) { $result['ISREGION'] = ''; } else { $result['ISREGION'] = ''; }
  if(ISCITY) { $result['ISCITY'] = ''; } else { $result['ISCITY'] = ''; }
  // заполнение формы
  if($_GET['editRec'] == 'new') {
    // новая запись каталога
    $rec_ind = '524320';
    $result['rec_form'] = 'new';  $result['page_ind'] = $ind;  $result['page_name'] = $page_name;
    $result['rec_ind'] = $rec_ind;  $result['name'] = $rec_name_default;
    $result['title'] = $result['meta_kwrds'] = $result['meta_dscrp'] = '';
    $result['caption'] = $result['info'] = $result['imgs'] = '';
    $result['is_visible'] = 'checked="checked"';
    $result['placement_tech'] = TODAY;  $result['update_tech'] = TODAY_TM;
    $result['placement'] = get_ltr_date(TODAY);  $result['updated'] = get_date_time(TODAY_TM);
    $tpl_content = file_get_contents(DIR_CORE.'inc'.DSR.'tpl'.DSR.'record.htm');
    $response = WSWIMGFORM.do_replaces($result, $tpl_content).CRNL;
  }
  else {    // $_GET['editRec'] = 'edit'
    // существующая запись каталога
    $rec_ind = (isset($_GET['recInd']))? intval($_GET['recInd']) : 0;
    // извлечь изображения
    $rss = miniDB::dbQuery('SELECT pos,src,alt FROM '.PFX.'images WHERE ext_ind='.$rec_ind.' AND ext_typ=\'rec\' ORDER BY pos;');
    $nmr = miniDB::dbNumRows($rss);  $imgs = '';
    for($k=0; $k<$nmr; $k++) {
      $rlt = miniDB::dbAssoc($rss);
      $src = '<img src="images/a'.$rlt['src'].'" alt="'.$rlt['alt'].'">';
      $imgs .= '<p class="imgs-prgrf" id="imgpos'.$rlt['pos'].'">'.$src.'<button class="cms-button__small imgs-button__rem" onclick="return imgRemove('.$rec_ind.','.$rlt['pos'].',\'rec\')">Удалить</button>&nbsp;<ins id="delayRem'.$rlt['pos'].'"></ins></p>'.CRNL;
    }
    $result['imgs'] = $imgs;
    // вывод формы
    $result['rec_form'] = 'edit';  $result['page_ind'] = $ind;  $result['page_name'] = $page_name;
    $result['rec_ind'] = $rec_ind;
    $resurs = miniDB::dbQuery('SELECT name,title,meta_kwrds,meta_dscrp,caption,info,vh,placement,updated FROM '.PFX.'records WHERE ind='.$rec_ind.';');
    $rlt = miniDB::dbAssoc($resurs);
    $result['title'] = $rlt['title'];  $result['meta_kwrds'] = $rlt['meta_kwrds'];  $result['meta_dscrp'] = $rlt['meta_dscrp'];
    $result['name'] = $rlt['name'];  $result['caption'] = $rlt['caption'];  $result['info'] = $rlt['info'];
    $result['is_visible'] = ($rlt['vh'] == 'v')? 'checked="checked"' : '';
    $result['placement_tech'] = $rlt['placement'];  $result['update_tech'] = $rlt['update'];
    $result['placement'] = get_ltr_date($rlt['placement']);  $result['updated'] = get_date_time($rlt['updated']);
    $tpl_content = file_get_contents(DIR_CORE.'inc'.DSR.'tpl'.DSR.'record.htm');
    $response = WSWIMGFORM.do_replaces($result, $tpl_content).CRNL;
  }
  miniDB::dbClose();
}

//// сохранить запись
elseif(isset($_POST['rec-form'])) {
  $page_ind = (isset($_POST['page-ind']))? intval($_POST['page-ind']) : 0;
  $rec_ind = (isset($_POST['rec-ind']))? intval($_POST['rec-ind']) : 0;
  $rec_name = (isset($_POST['rec-name']))? cut_str($_POST['rec-name'], ADMSTRSIZEMAX) : '';
  if(strlen($rec_name) < 2) $rec_name = $rec_name_default;
  $title  = (isset($_POST['title']))? parser(cut_str($_POST['title'])) : '';
  $kwords = (isset($_POST['meta-kwords']))? parser(cut_str($_POST['meta-kwords'])) : '';
  $dscrip = (isset($_POST['meta-dscrip']))? parser(cut_str($_POST['meta-dscrip'])) : '';
  $caption = (isset($_POST['rec-cap']))? cut_str($_POST['rec-cap'], ADMSTRSIZEMAX) : '';
  $info    = (isset($_POST['text-info']))? limit_text($_POST['text-info']) : '';
  $vh      = (isset($_POST['visible']))? 'v' : 'h';
  extDB::dbInit();
  if($_POST['rec-form'] == 'new') {
    // создать новую запись
    miniDB::dbQuery('UPDATE '.PFX.'pages SET att_ctlgs=\'y\' WHERE ind='.$page_ind.';');
    // даты создания и обновления записи
    $placement = $updated = '';
    // вставить новую запись
  //extDB::dbQuery('INSERT INTO '.PFX.'records (page_ind,name,title,meta_kwrds,meta_dscrp,caption,info,placement,updated) VALUES ('.$page_ind.',?,?,?,?,?,?,?,?,?);', $rec_name,$title,$kwords,$dscrip,$caption,$info,$vh,TODAY,TODAY_TM);
    miniDB::dbQuery('INSERT INTO '.PFX.'records (page_ind,name,title,meta_kwrds,meta_dscrp,caption,info,vh,placement,updated) VALUES ('.$page_ind.',\''.$rec_name.'\',\''.$caption.'\',\''.$title.'\',\''.$dscrip.'\',\''.$caption.'\',\''.$info.'\',\''.$vh.'\',\''.TODAY.'\',\''.TODAY_TM.'\');');
    // определить новый номер записи
    $rec_ind = miniDB::dbFetchCell('SELECT MAX(ind) FROM '.PFX.'records WHERE page_ind='.$page_ind.';');
  }
  else {    // $_POST['rec-form'] == 'edit'
    // перезаписать существующую запись
    //extDB::dbQuery('UPDATE '.PFX.'records SET name=?, title=?, meta_kwrds=?, meta_dscrp=?, caption=?, info=?,  vh=?, updated=? WHERE ind='.$rec_ind.';', $rec_name,$title,$kwords,$dscrip,$caption,$info,$vh,TODAY_TM);
    miniDB::dbQuery('UPDATE '.PFX.'records SET name=\''.$rec_name.'\', title=\''.$title.'\', meta_kwrds=\''.$kwords.'\', meta_dscrp=\''.$dscrip.'\', caption=\''.$caption.'\', info=\''.$info.'\',  vh=\''.$vh.'\', updated=\''.TODAY_TM.'\' WHERE ind='.$rec_ind.';');
  }
  // определить позицию последнего изображения
  $max_pos = miniDB::dbFetchCell('SELECT MAX(pos) FROM '.PFX.'images WHERE ext_ind='.$rec_ind.' AND ext_typ=\'rec\';');
  $max_pos ++;  $msg_sum = '';
  // закачать изображения на сервер
  if($_FILES) {
    foreach($_FILES[UPLOADFILEID]['error'] as $key => $error) {
      $msg = '';
      if ($error == UPLOAD_ERR_OK) {
        $file_ext = $_FILES[UPLOADFILEID]['type'][$key];
        if(!isset($imgtype[$file_ext])) {
          $msg = '<p class="fuplerr">Error. File is not a file of the image</p>'.CRNL;
        }
        elseif($_FILES[UPLOADFILEID]['size'][$key] > ADMFILESIZEMAX) {
          $msg = '<p class="fuplerr">Error. Size of the file too great</p>'.CRNL;
        }
        else {    // сохранить изображения
          $extrn = $imgtype[$file_ext];
          $filename = 'r'.$rec_ind.getrandname().'.'.$extrn;
          $img_src = $_FILES[UPLOADFILEID]['tmp_name'][$key];
          $img_dst = DIR_IMG.$filename;  $img_dst_small = DIR_IMG.DSR.'small'.DSR.$filename;
          $img_adm = DIR_ADM.'images'.DSR.'a'.$filename;
          $img_alt = (isset($_POST['imgAlt'][$key]))? parser(cut_str($_POST['imgAlt'][$key])) : '';
          $img_ref = (isset($_POST['imgRef'][$key]))? parser(cut_str($_POST['imgRef'][$key])) : '';
          $height_rec = (int)floor($ratio*IMGWIDTHREC);  $height_rec_small = (int)floor($ratio*IMGWIDTHRECSMALL);
          $height_adm = (int)floor($ratio*IMGWIDTHADMIN);
          $errdst = resizeimage($extrn, $img_src, $img_dst, IMGWIDTHREC, $height_rec);
          $errdst = resizeimage($extrn, $img_src, $img_dst_small, IMGWIDTHRECSMALL, $height_rec_small);
          $erradm = resizeimage($extrn, $img_src, $img_adm, IMGWIDTHADMIN, $height_adm);
          if(!$errdst or !$erradm) $msg = '<p class="fuplerr">Error of transformation '.strtoupper($extrn).' - file</p>'.CRNL;
          // удалить временный файл
          unlink($img_src);
        }
        if(empty($msg)) {
          // записать данные о изображении в БД
          miniDB::dbQuery('INSERT INTO '.PFX.'images (ext_ind,ext_typ,pos,src,alt,href) VALUES ('.$rec_ind.',\'rec\','.($max_pos + $key).',\''.$filename.'\',\''.$img_alt.'\',\''.$img_ref.'\');');
        }
      }
      $msg_sum .= $msg;
    }
  }
  // показать изображения
  $rss = miniDB::dbQuery('SELECT pos,src,alt FROM '.PFX.'images WHERE ext_ind='.$rec_ind.' AND ext_typ=\'rec\' ORDER BY pos;');
  $nmr = miniDB::dbNumRows($rss);  $imgs = '';
  for($k=0; $k<$nmr; $k++) {
    $rlt = miniDB::dbAssoc($rss);
    $src = '<img src="images/a'.$rlt['src'].'" alt="'.$rlt['alt'].'">';
    $imgs .= '<p class="imgs-prgrf" id="imgpos'.$rlt['pos'].'">'.$src.'<button class="cms-button__small imgs-button__rem" onclick="return imgRemove('.$rec_ind.','.$rlt['pos'].',\'rec\')">Удалить</button>&nbsp;<ins id="delayRem'.$rlt['pos'].'"></ins></p>'.CRNL;
  }
  $img_sum = $imgs.$msg_sum;
  // обновить каталог
  $collation=' updated DESC';  $rec_start = 0;  $sheet_num = 1;
  $rec_full = miniDB::dbFetchCell('SELECT COUNT(ind) FROM '.PFX.'records WHERE page_ind='.$page_ind.';');
  $resurs = miniDB::dbQuery('SELECT ind,name,vh FROM '.PFX.'records WHERE page_ind='.$page_ind.' ORDER BY'.$collation.' LIMIT '.$rec_start.','.ADMRECONPAGE.';');
  $num_rows = miniDB::dbNumRows($resurs);
  $sum = '';
  for($i=1; $i<=$num_rows; $i++) {
    $data = miniDB::dbAssoc($resurs);
    $ind = $data['ind']; $oi = ($i < 10)? '0'.$i : ''.$i;
    $vh = ($data['vh'] == 'v') ? VSBLREC : HDDNREC;
    $sum .= '<li><em class="r-num">'.$oi.'</em> '.$data['name'].' <span class="m-btn">'.$vh.EDTREC.$page_ind.','.$ind.BTNEND.DELREC.$page_ind.','.$ind.BTNEND.'</span></li>'.CRNL;
  }
  $response = $img_sum.'^'.$sum.'^'.show_switcher($sheet_num, $rec_full);
  miniDB::dbClose();
}

//// удалить изображение
elseif(isset($_GET['imgRemInd'])) {
  $index = intval($_GET['imgRemInd']);
  $img_pos = (isset($_GET['imgRemPos']))? intval($_GET['imgRemPos']) : 0;
  $img_typ = (isset($_GET['imgRemTyp']))? $_GET['imgRemTyp'] : 'absent';
  $db = miniDB::dbInit();
  $max_pos = miniDB::dbFetchCell('SELECT MAX(pos) FROM '.PFX.'images WHERE ext_ind='.$index.' AND ext_typ=\''.$img_typ.'\';');
  if(!(empty($max_pos) and ($img_pos <= $max_pos))) {
    $img_src = miniDB::dbFetchCell('SELECT src FROM '.PFX.'images WHERE ext_ind='.$index.' AND ext_typ=\''.$img_typ.'\' AND pos='.$img_pos.';');
    miniDB::dbQuery('DELETE FROM '.PFX.'images WHERE ext_ind='.$index.' AND ext_typ=\''.$img_typ.'\' AND pos='.$img_pos.';');
    for($k=$img_pos; $k<$max_pos; $k++) {
      miniDB::dbQuery('UPDATE '.PFX.'images SET pos='.$k.' WHERE ext_ind='.$index.' AND ext_typ=\''.$img_typ.'\' AND pos='.($k+1).';');
    }
    if($img_typ == 'sec') {
      unlink(DIR_IMG.$img_src);  unlink(DIR_ADM.'images'.DSR.'a'.$img_src);
    }
    elseif($img_typ == 'rec') {
      unlink(DIR_IMG.$img_src);  unlink(DIR_IMG.'small'.DSR.$img_src);  unlink(DIR_ADM.'images'.DSR.'a'.$img_src);
    }
    // вывод изображений
    $rss = miniDB::dbQuery('SELECT pos,src,alt FROM '.PFX.'images WHERE ext_ind='.$index.' AND ext_typ=\''.$img_typ.'\' ORDER BY pos;');
    $nmr = miniDB::dbNumRows($rss);  $imgs = '';
    for($k=0; $k<$nmr; $k++) {
      $rlt = miniDB::dbAssoc($rss);
      $src = '<img src="images/a'.$rlt['src'].'" alt="'.$rlt['alt'].'">';
      $imgs .= '<p class="imgs-prgrf" id="imgpos'.$rlt['pos'].'">'.$src.'<button class="cms-button__small imgs-button__rem" onclick="return imgRemove('.$index.','.$rlt['pos'].',\''.$img_typ.'\')">Удалить</button>&nbsp;<ins id="delayRem'.$rlt['pos'].'"></ins></p>'.CRNL;
    }
    $response = $imgs;
  }
  miniDB::dbClose();
}


header(CONTENT_HTML);
echo $response;
?>
