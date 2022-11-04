import EventEmitter from "events";
import { Browser, BrowserContextOptions } from 'playwright'

export class ActorsEnvironment extends EventEmitter {
	constructor() {
		super();
		this.browser = Browser;

	}

}
