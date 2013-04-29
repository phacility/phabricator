# Handlers

Handlers are simple classes that are used to parse response bodies and serialize request payloads.  All Handlers must extend the `MimeHandlerAdapter` class and implement two methods: `serialize($payload)` and `parse($response)`.  Let's build a very basic Handler to register for the `text/csv` mime type.

    <?php

    class SimpleCsvHandler extends \Httpful\Handlers\MimeHandlerAdapter
    {
        /**
         * Takes a response body, and turns it into 
         * a two dimensional array.
         *
         * @param string $body
         * @return mixed
         */
        public function parse($body)
        {
            return str_getcsv($body);
        }
    
        /**
         * Takes a two dimensional array and turns it
         * into a serialized string to include as the 
         * body of a request
         *
         * @param mixed $payload
         * @return string
         */
        public function serialize($payload)
        {
            $serialized = '';
            foreach ($payload as $line) {
                $serialized .= '"' . implode('","', $line) . '"' . "\n";
            }
            return $serialized;
        }
    }


Finally, you must register this handler for a particular mime type.

    Httpful::register('text/csv', new SimpleCsvHandler());

After this registering the handler in your source code, by default, any responses with a mime type of text/csv should be parsed by this handler.