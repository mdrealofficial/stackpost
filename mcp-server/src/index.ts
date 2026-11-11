#!/usr/bin/env node

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
  Tool,
} from "@modelcontextprotocol/sdk/types.js";
import crypto from "crypto";

const API_BASE_URL = (process.env.STACKPOSTS_API_URL || "http://127.0.0.1:8000")
  .replace(/\/+$/, "")
  .replace(/\/api\/automation\/v1$/, "");

const API_KEY = process.env.STACKPOSTS_API_KEY || "";

const FULL_API_BASE = `${API_BASE_URL}/api/automation/v1`;

async function callStackpostsApi(
  endpoint: string,
  method: "GET" | "POST" = "GET",
  body?: any,
  extraHeaders: Record<string, string> = {}
): Promise<{ ok: boolean; status: number; data: any }> {
  if (!API_KEY) {
    return {
      ok: false,
      status: 401,
      data: {
        error:
          "Missing STACKPOSTS_API_KEY. Please provide STACKPOSTS_API_KEY environment variable.",
      },
    };
  }

  const url = `${FULL_API_BASE}${endpoint.startsWith("/") ? endpoint : "/" + endpoint}`;
  const headers: Record<string, string> = {
    Accept: "application/json",
    Authorization: `Bearer ${API_KEY}`,
    "User-Agent": "Stackposts-MCP-Server/1.0",
    ...extraHeaders,
  };

  if (body) {
    headers["Content-Type"] = "application/json";
  }

  try {
    const res = await fetch(url, {
      method,
      headers,
      body: body ? JSON.stringify(body) : undefined,
    });

    const text = await res.text();
    let parsed: any;
    try {
      parsed = JSON.parse(text);
    } catch {
      parsed = { raw: text };
    }

    return {
      ok: res.ok,
      status: res.status,
      data: parsed,
    };
  } catch (err: any) {
    return {
      ok: false,
      status: 500,
      data: {
        error: `Failed to connect to Stackposts at ${url}: ${err.message}`,
      },
    };
  }
}

const TOOLS: Tool[] = [
  {
    name: "stackposts_ping",
    description:
      "Verify connection to the Stackposts instance and test authentication with your API key.",
    inputSchema: {
      type: "object",
      properties: {},
      required: [],
    },
  },
  {
    name: "stackposts_list_accounts",
    description:
      "List all connected social media channels/accounts (Facebook Pages, Instagram, Twitter/X, LinkedIn, TikTok, etc.) with their IDs, usernames, and capabilities.",
    inputSchema: {
      type: "object",
      properties: {},
      required: [],
    },
  },
  {
    name: "stackposts_list_posts",
    description:
      "List recent social posts from Stackposts, optionally filtered by status (draft, scheduled, published, failed).",
    inputSchema: {
      type: "object",
      properties: {
        status: {
          type: "string",
          enum: ["all", "draft", "scheduled", "published", "failed"],
          description: "Filter posts by status. Defaults to all.",
        },
        limit: {
          type: "number",
          description: "Max number of posts to return (1-100, default: 20).",
        },
      },
      required: [],
    },
  },
  {
    name: "stackposts_get_post",
    description:
      "Get detailed information about a specific post by its ID or id_secure string.",
    inputSchema: {
      type: "object",
      properties: {
        id: {
          type: "string",
          description: "The numeric ID or unique id_secure of the post.",
        },
      },
      required: ["id"],
    },
  },
  {
    name: "stackposts_create_post",
    description:
      "Draft, schedule, or immediately publish a social media post across one or more connected social accounts.",
    inputSchema: {
      type: "object",
      properties: {
        account_ids: {
          type: "array",
          items: { type: "number" },
          description:
            "List of social account IDs to publish to (get IDs from stackposts_list_accounts).",
        },
        caption: {
          type: "string",
          description: "The text content or caption of the social post.",
        },
        media_urls: {
          type: "array",
          items: { type: "string" },
          description:
            "Optional array of public HTTP/HTTPS URLs of images or videos to attach.",
        },
        mode: {
          type: "string",
          enum: ["draft", "scheduled", "publish_now"],
          description:
            "Posting mode: 'draft' saves as draft, 'scheduled' queues for later, 'publish_now' publishes immediately. Default: 'scheduled'.",
        },
        schedule_at: {
          type: "string",
          description:
            "Target schedule date/time (e.g. '2026-11-20 14:00:00' or ISO 8601). Required if mode is 'scheduled'.",
        },
        timezone: {
          type: "string",
          description:
            "Optional timezone (e.g. 'America/New_York', 'UTC', 'Asia/Dhaka').",
        },
        notes: {
          type: "string",
          description: "Internal reference note or campaign tag.",
        },
      },
      required: ["account_ids", "caption"],
    },
  },
];

const server = new Server(
  {
    name: "stackposts-mcp-server",
    version: "1.0.0",
  },
  {
    capabilities: {
      tools: {},
    },
  }
);

server.setRequestHandler(ListToolsRequestSchema, async () => {
  return { tools: TOOLS };
});

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  try {
    switch (name) {
      case "stackposts_ping": {
        const res = await callStackpostsApi("/me", "GET");
        return {
          content: [
            {
              type: "text",
              text: JSON.stringify(res.data, null, 2),
            },
          ],
          isError: !res.ok,
        };
      }

      case "stackposts_list_accounts": {
        const res = await callStackpostsApi("/accounts", "GET");
        return {
          content: [
            {
              type: "text",
              text: JSON.stringify(res.data, null, 2),
            },
          ],
          isError: !res.ok,
        };
      }

      case "stackposts_list_posts": {
        const status = (args?.status as string) || "all";
        const limit = (args?.limit as number) || 20;
        const queryParams = new URLSearchParams();
        if (status !== "all") {
          queryParams.set("status", status);
        }
        queryParams.set("limit", String(limit));

        const res = await callStackpostsApi(`/posts?${queryParams.toString()}`, "GET");
        return {
          content: [
            {
              type: "text",
              text: JSON.stringify(res.data, null, 2),
            },
          ],
          isError: !res.ok,
        };
      }

      case "stackposts_get_post": {
        const id = args?.id as string;
        if (!id) {
          return {
            content: [{ type: "text", text: "Error: Missing required argument 'id'" }],
            isError: true,
          };
        }
        const res = await callStackpostsApi(`/posts/${encodeURIComponent(id)}`, "GET");
        return {
          content: [
            {
              type: "text",
              text: JSON.stringify(res.data, null, 2),
            },
          ],
          isError: !res.ok,
        };
      }

      case "stackposts_create_post": {
        const idempotencyKey = crypto.randomUUID();
        const payload: Record<string, any> = {
          account_ids: args?.account_ids,
          caption: args?.caption,
          mode: args?.mode || "scheduled",
        };

        if (Array.isArray(args?.media_urls) && args.media_urls.length > 0) {
          payload.media_urls = args.media_urls;
        }

        if (args?.schedule_at) {
          payload.schedule_at = args.schedule_at;
        }

        if (args?.timezone) {
          payload.timezone = args.timezone;
        }

        if (args?.notes) {
          payload.notes = args.notes;
        }

        const res = await callStackpostsApi("/posts", "POST", payload, {
          "Idempotency-Key": idempotencyKey,
        });

        return {
          content: [
            {
              type: "text",
              text: JSON.stringify(res.data, null, 2),
            },
          ],
          isError: !res.ok,
        };
      }

      default:
        return {
          content: [{ type: "text", text: `Unknown tool name: ${name}` }],
          isError: true,
        };
    }
  } catch (err: any) {
    return {
      content: [{ type: "text", text: `Tool error: ${err.message}` }],
      isError: true,
    };
  }
});

async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error("Stackposts MCP Server running on stdio");
}

main().catch((err) => {
  console.error("Fatal error starting Stackposts MCP Server:", err);
  process.exit(1);
});

