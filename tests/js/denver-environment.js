/**
 * jsdom test environment running in America/Denver, for tests that must
 * prove a label doesn't shift with the browser's time zone. The real
 * process TZ is set here because test files only see a copy of process.env.
 */
const JsdomEnvironment = require( 'jest-environment-jsdom' ).default;

class DenverEnvironment extends JsdomEnvironment {
	async setup() {
		this.previousTz = process.env.TZ;
		process.env.TZ = 'America/Denver';
		await super.setup();
	}

	async teardown() {
		if ( this.previousTz === undefined ) {
			delete process.env.TZ;
		} else {
			process.env.TZ = this.previousTz;
		}
		await super.teardown();
	}
}

module.exports = DenverEnvironment;
