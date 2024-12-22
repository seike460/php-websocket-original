# php-websocket-original

# WebSocket Server in PHP

This project is a simple WebSocket server implemented in PHP using the socket extension. It provides a basic framework to handle WebSocket connections, send and receive messages, and manage client interactions.

## Features

- Listens for WebSocket connections on a specified address and port.
- Handles multiple client connections.
- Demonstrates the basic setup for creating a WebSocket server in PHP.

## Requirements

- PHP 7.4 or later.
- The PHP `sockets` extension enabled.
- A server environment to run the script (e.g., Linux, macOS, or a Docker container).

## Installation

1. Clone this repository:
   ```bash
   git clone https://github.com/your-username/your-repo-name.git
   cd your-repo-name
   ```

2. Ensure PHP and the `sockets` extension are installed and enabled:
   ```bash
   php -m | grep sockets
   ```
   If not installed, use your package manager to install it (e.g., `sudo apt install php-sockets` on Ubuntu).

3. Place the script in the desired directory and update file permissions if necessary.

## Usage

1. Open the `websokects.php` file and configure the following variables as needed:
   - `$address`: The IP address to bind the server (default is `0.0.0.0`, which listens on all available interfaces).
   - `$port`: The port number for the WebSocket server (default is `8080`).

2. Start the WebSocket server:
   ```bash
   php websokects.php
   ```

3. The server will start listening for connections. Connect a WebSocket client to `ws://<server-ip>:<port>`.

## Example WebSocket Client

You can use any WebSocket client to test the server. For example, in JavaScript:

```javascript
const socket = new WebSocket('ws://localhost:8080');

socket.onopen = () => {
    console.log('Connected to the WebSocket server.');
    socket.send('Hello, server!');
};

socket.onmessage = (event) => {
    console.log('Message from server:', event.data);
};

socket.onerror = (error) => {
    console.error('WebSocket error:', error);
};

socket.onclose = () => {
    console.log('WebSocket connection closed.');
};
```

## Notes

- This is a basic implementation and does not include advanced features like SSL/TLS encryption or authentication. For production use, consider adding `wss://` support and robust error handling.
- Make sure the server's port is open in your firewall.

## Troubleshooting

- If the server fails to start, check the PHP error log or ensure that the port is not already in use.
- Verify that the `sockets` extension is enabled in your PHP configuration.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Contributions

Contributions are welcome! Feel free to submit issues or pull requests to enhance the functionality or documentation.
