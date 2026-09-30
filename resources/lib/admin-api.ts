import type { SocialImageConfig } from "@/types/admin";

export class AdminApi {
  public constructor(private readonly config: SocialImageConfig) {}

  public async request<T>(path: string, init: RequestInit = {}): Promise<T> {
    const endpoint = `${this.config.restUrl.replace(/\/$/, "")}${path}`;
    const response = await fetch(endpoint, {
      credentials: "same-origin",
      ...init,
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": this.config.nonce,
        ...init.headers,
      },
    });
    const body = (await response.json().catch(() => ({}))) as {
      code?: string;
      message?: string;
    } & T;

    if (!response.ok || body.code) {
      throw new Error(body.message || `Request failed (${response.status})`);
    }

    return body;
  }
}
