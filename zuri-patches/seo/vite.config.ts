import { defineConfig } from "vite";
import react from "@vitejs/plugin-react-swc";
import path from "path";
import fs from "fs";
import { componentTagger } from "lovable-tagger";

const BASE_URL = "https://zuriafricaadventures.com";

// SECURITY + SEO NOTE: Only PUBLIC, indexable routes belong here.
// NEVER include auth-gated pages (/my-itinerary, /portal, /my-safari, /payment,
// /auth/*, etc.) — doing so generates static HTML files that Google can read,
// causing them to appear in search previews even though they carry noindex.
// The noindex <meta> tag only prevents listing in search results; it does NOT
// prevent the description text from briefly surfacing in rich results or GSC.
// The safest rule: if a page has <SEOHead noindex />, it stays off this list.
const SEO_ROUTES: Record<string, { title: string; description: string }> = {
  "/": {
    title: "Book African Safaris Online | Instant Pricing | Expert Support",
    description:
      "Browse 170+ African Safaris with instant pricing. Customize in Minutes, book online, manage from your itinerary dashboard - Full-time Expert support available",
  },
  "/safaris": {
    title: "All Safaris | Explore 170+ Curated African Safari Experiences | ZURI Africa Adventures",
    description:
      "Explore, Search and Customize your dream African Safari from over 170+ expertly curated African Safari Experiences, with exclusivity and ZURI expert guidance.",
  },
  "/safari-planner": {
    title: "Safari Planner | Design Your Dream African Safari | ZURI Africa Adventures",
    description:
      "Use our smart Safari Planner to filter, compare, and customize the perfect African safari — Big Five, gorilla trekking, family, luxury, and more.",
  },
  "/community": {
    title: "Zuri Community | Traveler Stories, Posts and Reviews | ZURI Africa Adventures",
    description:
      "Join the ZURI safari community. Share and Post your African Safari experiences, read authentic reviews and posts, and get inspired for your next safari journey.",
  },
  "/discover": {
    title: "Discover ZURI | Africa's First Digital Safari Companion",
    description:
      "Discover how ZURI Africa Adventures is transforming the African safari booking experience with instant pricing, smart customization, and expert support.",
  },
  "/about": {
    title: "About Zuri Africa Adventures",
    description:
      "We curate premium African safaris powered by the Zuri Safari Navigator - Africa's first end-to-end exclusive and digital safari platform for our clients!",
  },
  "/book-meeting": {
    title: "Book a Meeting with ZURI | African Safari Consultation",
    description:
      "Schedule a free consultation with a ZURI safari expert. Get personalised advice, itinerary reviews, and answers to all your African safari questions.",
  },
  "/privacy-policy": {
    title: "Privacy Policy | ZURI Africa Adventures",
    description:
      "Read the ZURI Africa Adventures privacy policy. Learn how we protect your data and ensure a secure safari booking experience.",
  },
  "/terms-of-service": {
    title: "Terms of Service | ZURI Africa Adventures",
    description:
      "Read the ZURI Africa Adventures terms of service for booking safaris, using our platform, and understanding your rights as a traveler.",
  },
};

function seoMetaPlugin() {
  return {
    name: "seo-meta-generator",
    closeBundle: async () => {
      const distDir = path.resolve(__dirname, "dist");
      const templatePath = path.join(distDir, "index.html");

      if (!fs.existsSync(templatePath)) return;

      const template = fs.readFileSync(templatePath, "utf-8");

      for (const [route, meta] of Object.entries(SEO_ROUTES)) {
        const canonical = `${BASE_URL}${route}`;
        let html = template;

        html = html.replace(/<title>[^<]*<\/title>/, `<title>${meta.title}</title>`);
        html = html.replace(
          /<meta\s+name="title"\s+content="[^"]*"/,
          `<meta name="title" content="${meta.title}"`
        );
        html = html.replace(
          /<meta\s+name="description"\s+content="[^"]*"/,
          `<meta name="description" content="${meta.description}"`
        );
        html = html.replace(
          /<meta\s+property="og:title"\s+content="[^"]*"/,
          `<meta property="og:title" content="${meta.title}"`
        );
        html = html.replace(
          /<meta\s+property="og:description"\s+content="[^"]*"/,
          `<meta property="og:description" content="${meta.description}"`
        );
        html = html.replace(
          /<meta\s+property="og:url"\s+content="[^"]*"/,
          `<meta property="og:url" content="${canonical}"`
        );
        html = html.replace(
          /<meta\s+name="twitter:title"\s+content="[^"]*"/,
          `<meta name="twitter:title" content="${meta.title}"`
        );
        html = html.replace(
          /<meta\s+name="twitter:description"\s+content="[^"]*"/,
          `<meta name="twitter:description" content="${meta.description}"`
        );
        html = html.replace(
          /<link\s+rel="canonical"\s+href="[^"]*"/,
          `<link rel="canonical" href="${canonical}"`
        );

        // Write to dist/[route]/index.html (skip root — it's already dist/index.html)
        if (route !== "/") {
          const routeDir = path.join(distDir, route.slice(1));
          fs.mkdirSync(routeDir, { recursive: true });
          fs.writeFileSync(path.join(routeDir, "index.html"), html);
        }
      }

      console.log(`[SEO] Generated static HTML for ${Object.keys(SEO_ROUTES).length} public routes`);
    },
  };
}

export default defineConfig(({ mode }) => ({
  server: {
    host: "::",
    port: 8080,
  },
  define: {
    __BUILD_ID__: JSON.stringify(new Date().toISOString()),
  },
  plugins: [
    react(),
    mode === "development" && componentTagger(),
    mode === "production" && seoMetaPlugin(),
  ].filter(Boolean),
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
      react: path.resolve(__dirname, "node_modules/react"),
      "react-dom": path.resolve(__dirname, "node_modules/react-dom"),
    },
    dedupe: ["react", "react-dom"],
  },
}));
