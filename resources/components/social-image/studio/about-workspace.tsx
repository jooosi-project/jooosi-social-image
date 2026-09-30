import type { ComponentType } from "react";

import IconAward from "~icons/lucide/award";
import IconBraces from "~icons/lucide/braces";
import IconCoffee from "~icons/lucide/coffee";
import IconExternalLink from "~icons/lucide/external-link";
import IconFileText from "~icons/lucide/file-text";
import IconGithub from "~icons/lucide/github";
import IconImage from "~icons/lucide/image";
import IconLayers from "~icons/lucide/layers-3";
import IconPackage from "~icons/lucide/package";
import IconRefresh from "~icons/lucide/refresh-cw";
import IconUsers from "~icons/lucide/users";
import SocialImageLogo from "~/jooosi-social-image.svg?react";

import { Frame, FrameFooter, FrameHeader, FramePanel } from "@/components/reui/frame";
import { buttonVariants } from "@/components/ui/button";
import JooosiSponsorIcon from "@/icons/jooosi.svg?react";
import LiveCanvasSponsorIcon from "@/icons/livecanvas.svg?react";
import { cn } from "@/lib/utils";

type Capability = {
  title: string;
  description: string;
  icon: ComponentType<{ className?: string }>;
};

type Sponsor = {
  name: string;
  description: string;
  href: string;
  icon: ComponentType<{ className?: string }>;
};

const capabilities: Capability[] = [
  {
    title: "Visual image editor",
    description: "Compose branded layouts from text, images, shapes, SVG icons, and reusable templates.",
    icon: IconLayers,
  },
  {
    title: "Dynamic WordPress data",
    description: "Connect designs to posts, authors, taxonomies, custom fields, and featured images.",
    icon: IconBraces,
  },
  {
    title: "Social and featured images",
    description: "Generate Open Graph, X/Twitter, and optional Media Library featured images.",
    icon: IconImage,
  },
  {
    title: "Automatic regeneration",
    description: "Queue rendering in the background and invalidate only the cached images that changed.",
    icon: IconRefresh,
  },
];

const sponsors: Sponsor[] = [
  {
    name: "Jooosi",
    description: "Open-source tools and products for WordPress.",
    href: "https://jooo.si",
    icon: JooosiSponsorIcon,
  },
  {
    name: "LiveCanvas",
    description: "A visual site builder for WordPress.",
    href: "https://livecanvas.com",
    icon: LiveCanvasSponsorIcon,
  },
];

const sponsorshipBenefits: Capability[] = [
  {
    title: "Release visibility",
    description: "Your brand icon can ship with supported plugin releases.",
    icon: IconPackage,
  },
  {
    title: "Project documentation",
    description: "Sponsors are recognized across plugin documentation.",
    icon: IconFileText,
  },
  {
    title: "Admin recognition",
    description: "Featured support sustains ongoing plugin development.",
    icon: IconAward,
  },
  {
    title: "Developer reach",
    description: "Connect with the wider WordPress developer community.",
    icon: IconUsers,
  },
];

export function AboutWorkspace({ version }: { version: string }) {
  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 px-5 py-6 lg:px-8 lg:py-8">
      <section className="overflow-hidden rounded-2xl border bg-card shadow-sm" aria-labelledby="social-image-about-heading">
        <div className="flex flex-col gap-6 p-6 md:flex-row md:items-center md:justify-between lg:p-8">
          <div className="flex min-w-0 items-center gap-5">
            <span className="grid size-16 shrink-0 place-items-center rounded-2xl bg-foreground text-background shadow-sm">
              <SocialImageLogo className="size-16 rounded-2xl bg-background text-foreground" aria-hidden="true" />
            </span>
            <div className="min-w-0">
              <div className="mb-2 flex flex-wrap items-center gap-2">
                <h1 id="social-image-about-heading" className="text-2xl font-semibold tracking-tight">Jooosi Social Image</h1>
                <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-foreground">Version {version}</span>
              </div>
              <p className="max-w-3xl text-sm leading-6 text-muted-foreground">
                Design dynamic Open Graph and featured images directly in WordPress. Connect layouts to live content, assign them with flexible rules, and let Social Image regenerate cached images when content changes.
              </p>
            </div>
          </div>
          <a
            className={cn(buttonVariants({ variant: "outline", size: "sm" }), "shrink-0")}
            href="https://github.com/jooosi-project/jooosi-social-image"
            target="_blank"
            rel="noopener noreferrer"
          >
            <IconGithub aria-hidden="true" /> GitHub repository
          </a>
        </div>

        <div className="grid border-t sm:grid-cols-2 lg:grid-cols-4">
          {capabilities.map((capability, index) => {
            const Icon = capability.icon;

            return (
              <div
                key={capability.title}
                className={cn(
                  "flex min-w-0 flex-col gap-3 p-5",
                  index % 2 === 0 && "sm:border-r",
                  index < 2 && "border-b lg:border-b-0",
                  index > 0 && "lg:border-l",
                )}
              >
                <span className="grid size-9 place-items-center rounded-lg border bg-muted/50" aria-hidden="true">
                  <Icon className="size-4" />
                </span>
                <div>
                  <h2 className="text-sm font-semibold">{capability.title}</h2>
                  <p className="mt-1 text-sm leading-5 text-muted-foreground">{capability.description}</p>
                </div>
              </div>
            );
          })}
        </div>
      </section>

      <div className="grid gap-5 lg:grid-cols-[minmax(0,1.1fr)_minmax(20rem,0.9fr)]">
        <Frame aria-labelledby="social-image-sponsorship-heading">
          <FrameHeader>
            <h2 id="social-image-sponsorship-heading" className="text-base font-semibold">Open-source sponsorship</h2>
            <p className="mt-1 text-sm text-foreground/70">
              Every contribution supports maintenance across the complete WordPress plugin portfolio.
            </p>
          </FrameHeader>
          <FramePanel>
            <div className="grid sm:grid-cols-2">
              {sponsorshipBenefits.map((benefit, index) => {
                const Icon = benefit.icon;

                return (
                  <div
                    key={benefit.title}
                    className={cn(
                      "flex items-start gap-3 border-b p-4 last:border-b-0",
                      index % 2 === 0 && "sm:border-r",
                      index >= 2 && "sm:border-b-0",
                    )}
                  >
                    <span className="grid size-9 shrink-0 place-items-center rounded-lg border bg-muted/50" aria-hidden="true">
                      <Icon className="size-4" />
                    </span>
                    <div className="min-w-0">
                      <h3 className="text-sm font-medium">{benefit.title}</h3>
                      <p className="mt-1 text-xs leading-5 text-muted-foreground">{benefit.description}</p>
                    </div>
                  </div>
                );
              })}
            </div>
          </FramePanel>
          <FrameFooter className="gap-2 sm:flex-row">
            <a
              className={cn(buttonVariants({ size: "sm" }), "flex-1")}
              href="https://github.com/sponsors/suasgn"
              target="_blank"
              rel="noopener noreferrer"
            >
              <IconGithub aria-hidden="true" /> GitHub Sponsors
            </a>
            <a
              className={cn(buttonVariants({ variant: "outline", size: "sm" }), "flex-1")}
              href="https://ko-fi.com/Q5Q75XSF7"
              target="_blank"
              rel="noopener noreferrer"
            >
              <IconCoffee aria-hidden="true" /> Ko-fi
            </a>
          </FrameFooter>
        </Frame>

        <Frame aria-labelledby="social-image-sponsors-heading">
          <FrameHeader>
            <h2 id="social-image-sponsors-heading" className="text-base font-semibold">Proudly sponsored by</h2>
            <p className="mt-1 text-sm text-foreground/70">Partners supporting Jooosi Social Image and its open-source development.</p>
          </FrameHeader>
          <FramePanel>
            {sponsors.map((sponsor, index) => {
              const SponsorIcon = sponsor.icon;

              return (
                <a
                  key={sponsor.name}
                  className={cn(
                    "group flex items-center gap-4 p-5 transition-colors hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset",
                    index < sponsors.length - 1 && "border-b",
                  )}
                  href={sponsor.href}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <span className="grid size-12 shrink-0 place-items-center rounded-xl border bg-muted/50 text-foreground" aria-hidden="true">
                    <SponsorIcon className="size-7" />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block text-sm font-semibold">{sponsor.name}</span>
                    <span className="mt-0.5 block text-xs leading-5 text-muted-foreground">{sponsor.description}</span>
                  </span>
                  <IconExternalLink className="size-4 text-muted-foreground transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5" aria-hidden="true" />
                </a>
              );
            })}
          </FramePanel>
        </Frame>
      </div>

      <p className="text-center text-xs text-muted-foreground">GPL-3.0-or-later · Open source</p>
    </div>
  );
}
