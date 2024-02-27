import http from "http";
import { API, getTimestamp } from "../../utils/index.js";

// TODO: create logging received messages
class WebHookService {
    constructor(unit, config) {
        this.unit = unit;
        this.config = config;
        this.server = http.createServer(this.requestListener.bind(this));
    }

    async requestListener(request, response) {
        if (request.url === this.config?.apiEndpoint && request.method === "GET") {
            response.writeHead(202, { 'Content-Type': 'application/json' })
            response.end(JSON.stringify({ message: "GET request received." }));
            await this.getEventHandler(request, response)
        } else if (request.url === this.config?.apiEndpoint && request.method === "POST") {
            try {
                let data = '';
                request.on('data', (chunk) => {
                    data += chunk;
                });

                request.on('end', async () => {
                    if (!data) {
                        response.writeHead(400, { 'Content-Type': 'application/json' })
                        response.end(JSON.stringify({ message: "Request body is empty." }));
                        this.logToConsole(getTimestamp(new Date()), request.connection.remoteAddress, null, 'Request body is empty');
                        return;
                    }
                    const jsonData = JSON.parse(data);
                    await this.postEventHandler(request, jsonData);

                    response.writeHead(202, { 'Content-Type': 'application/json' })
                    response.end(JSON.stringify({ message: "Webhook received and processed." }));
                })
            } catch (error) {
                console.error(error.message)
            }
        } else {
            response.writeHead(405, { 'Content-Type': 'application/json' });
            response.end(JSON.stringify({ message: "Method not allowed." }));
        }
    }

    // handle POST request
    async postEventHandler(request, data = null) {
    }

    // handle GET request
    async getEventHandler(request, data = null) {
    }

    /**
     * Local logging, used server timestamp
     * @param now timestamp
     * @param host IP address
     * @param subId unique device identifier if required
     * @param msg event message
     */
    logToConsole(now, host = null, subId = null, msg) {
        console.log(`${ now } || ${ host ? host : subId } || ${ msg }`);
    }

    /**
     * Create a log message based on the provided data object.
     * @param {Object} data The data object from which to create the log message.
     * @param {Array<string>} [exclude=[]] An optional array of keys to exclude from the log message.
     * @returns {string} The log message generated from the data object.
     */
    createLogMessage(data, exclude = []) {
        return Object.entries(data)
            .filter(([key]) => !exclude.includes(key))
            .map(([key, value]) => `${key}: '${value}'`)
            .join(', ');
    }

    /**
     * Send an event message to remote storage
     * @param now timestamp
     * @param host IP address
     * @param subId unique device identifier if required
     * @param unit device name
     * @param msg event message
     * @returns {Promise<void>}
     */
    async sendToSyslogStorage(now, host = null, subId = null, unit = 'noName', msg) {
        await API.sendLog({ date: now, ip: host, subId, unit: this.unit, msg });
    }

    start() {
        this.server.listen(this.config.port, () => {
            console.log(`${ this.unit.toUpperCase() } Webhook server is listening on port ${ this.config.port }`);
        });
    }
}

export { WebHookService }
