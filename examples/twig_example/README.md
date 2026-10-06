# Twig example

Subclasses `Ham\App` to render templates with Twig. The templates use inheritance (`base.html` → `home.html`).

```sh
composer install
php -S localhost:8000 index.php
```

`http://localhost:8000/about` then renders:

```html
<h2>about</h2>

    hi from the about page
```
