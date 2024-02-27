import syslogServer from "syslog-server";
import { API, getTimestamp, isIpAddress, parseSyslogMessage } from "../../utils/index.js";
import config from "../../config.json" assert { type: "json"}

const { topology } = config;
const mode = process.env.NODE_ENV || "";

class SyslogService {
    constructor(unit, config, spamWords = []) {
        this.unit = unit;
        this.config = config;
        this.spamWords = spamWords;
    }

    /**
     * Checks if a given message contains spam words.
     * @param {string} message - The message to be checked for spam content.
     * @returns {boolean} True if the message contains any spam words, otherwise false.
     */
    isSpamMessage(message) {
        return this.spamWords.some(keyword => message.includes(keyword));
    }

    /**
     * Local logging, used server timestamp
     * @param now
     * @param host
     * @param msg
     */
    logToConsole(now, host, msg) {
        console.log(`${ now } || ${ host } || ${ msg }`);
    }

    /**
     * Send an event message to remote storage
     * @param now timestamp
     * @param host IP address
     * @param msg event message
     * @returns {Promise<void>}
     */
    async sendToSyslogStorage(now, host, msg) {
        await API.sendLog({ date: now, ip: host, unit: this.unit, msg });
    }

    createSyslogServer() {
        const syslog = new syslogServer();

        syslog.on("message", async ({ date, host, message }) => {
            const now = getTimestamp(date);// Get server timestamp
            let { hostname: addressFromMessageBody, message: msg } = parseSyslogMessage(message);

            //  Check hostname from syslog message body
            if (topology?.nat && isIpAddress(addressFromMessageBody)) {
                host = addressFromMessageBody;
            }

            /**
             * TODO:
             *      - refactor syslog parser for BSD syslog messages.
             *      - temporarily use handler
             */
            if (!msg) {
                console.error("Parse message failed: " + message);
                return
            }

            /**
             * Filtering spam syslog messages in production mode
             */
            if (mode !== "development" && this.isSpamMessage(msg)) {
                return;
            }

            // Local and remote logging
            this.logToConsole(now, host, msg);
            await this.sendToSyslogStorage(now, host, msg);

            // Running handlers
            this.handleSyslogMessage(now, host, msg);
        });

        syslog.on("error", (err) => {
            console.error(err.message);
        });

        syslog.start({ port: this.config.port }).then(() => {
            console.log(`${ this.unit.toUpperCase() } syslog server running on UDP port ${ this.config.port } || NAT is ${ topology?.nat || false } || mode: ${ mode }`);
        });
    }

    handleSyslogMessage(now, host, msg) {
        console.log("RUN handleSyslogMessage")
    }
}

export { SyslogService };