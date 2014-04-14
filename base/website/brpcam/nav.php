<ul class="nav nav-pills pull-right">
<?php

$navs = array('index.php' =>
                array('title' => 'Home',
                      'uri' => 'index.php',
                      'active' => false),
              'about.php' =>
                array('title' => 'About',
                      'uri' => 'about.php',
                      'active' => false)
             );

$self_uri = end(explode('/', $_SERVER["REQUEST_URI"]));
if (isset($navs[$self_uri]))
    $navs[$self_uri]['active'] = true;
else
    $navs['index.php']['active'] = true;

foreach ($navs as $i => $nav) {
    if ($nav['active'])
        echo '<li class="active"><a href="'.$nav['uri'].'">'.$nav['title'].'</a></li>';
    else 
        echo '<li><a href="'.$nav['uri'].'">'.$nav['title'].'</a></li>';
}
?>
</ul>
