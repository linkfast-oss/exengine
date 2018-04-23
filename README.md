# ExEngine Microframework

## Quick start

1. Install using `composer` o download `CoreX.php` file.

    ```
    composer install linkfast-oss/exengine
    ````

2. Create a loader

    Create an `index.php` file in the root of the folder exposed to the HTTP server, and include there `vendor.php` if using `composer` or the `CoreX.php` file.

    ```php
    <?php
        include_once 'CoreX.php';
        // or
        include_once 'vendor/autoload.php';

        new \ExEngine\CoreX();
    ```

3. Create a folder called `_` relative to `index.php`. Inside of this new folder, create a file called `Test.php` with the following contents:

    ```php
    <?php
        class Test {
            function helloworld() {
                return "<h1>Hello World</h1>";
            }
        }
    ```

4. Open your browser and navigate to: `http://myserverhost/index.php/Test/helloworld`

5. Take a look to the `Examples` folder, profit.

## Creating a REST controller

ExEngine allows easy REST controllers creation, you just have to extend a parent class and write the HTTP methods responses.

```php
<?php
    class RestExample extends \ExEngine\Rest {
        function get($id) {
            return "Hello $id";
        }

        function post() {
            $data = $_POST['data'];
            return "Data: $data";
        }
        // function put()
        // function delete()
        // function options()
        // etc.
    }
```

## Writing a JSON api

ExEngine converts anything except `strings` functions results to JSON, encapsulating in an standard response.

Example response:
```json
    
```

## License

```
The MIT License (MIT)

Copyright (c) 2018 LinkFast S.A.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE
OR OTHER DEALINGS IN THE SOFTWARE.
```