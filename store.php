<?php

namespace DevCoder;

class DotEnv
{
    /**
     * The directory where the .env file is located.
     *
     * @var string
     */
    protected $path;

    /**
     * DotEnv constructor.
     *
     * @param string $path Path to the .env file.
     * @throws \InvalidArgumentException
     */
    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf('The .env file %s does not exist.', $path));
        }

        if (!is_readable($path)) {
            throw new \RuntimeException(sprintf('The .env file %s is not readable.', $path));
        }

        $this->path = $path;
    }

    /**
     * Load the environment variables from the .env file.
     *
     * @return void
     * @throws \RuntimeException
     */
    public function load(): void
    {
        // Read file lines into an array
        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new \RuntimeException('Failed to read the .env file.');
        }

        // Process each line
        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments (lines starting with # or empty lines)
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            // Split the line into key and value
            $parts = explode('=', $line, 2);

            if (count($parts) !== 2) {
                continue; // Skip malformed lines
            }

            $name = trim($parts[0]);
            $value = trim($parts[1]);

            // Avoid overriding existing variables in $_ENV and $_SERVER
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                // Set the environment variable
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;

                // Also set the environment variable globally if required (optional)
                // putenv(sprintf('%s=%s', $name, $value)); // Uncomment if needed for global access
            }
        }
    }
}

?>
