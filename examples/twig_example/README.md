##Twig Example


This is a very simple example showing how to sub-class Ham to incorporate the twig template engine.

To Use:

*   First install dependencys:

```bash
$ composer.phar update
```

*   then you can run with the builtin php server

```bash
$ php -S 0.0.0.0:8000 .htrouter.php
```


*   now going to `localhost:8000/about`

displays:

```html
<h2>about</h2>

    hi from the about page
```

also if you look in the templates they are using inheritence


_base.html -> home.html_


