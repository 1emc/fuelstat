# Node-Version zentral; Hauptversionen bewusst umstellen (Dependabot schlägt sie nicht vor, siehe .github/dependabot.yml)
ARG NODE_VERSION=24

# Gemeinsame Basis
FROM node:${NODE_VERSION}-alpine AS base
WORKDIR /usr/src/app
COPY package*.json ./

# Entwicklung (nur lokal, siehe infra/docker-compose.dev.yml): Code wird eingebunden, nodemon lädt neu
FROM base AS dev
ENV NODE_ENV=development
RUN npm ci && npm install -g nodemon@3
COPY . .
EXPOSE 3000
CMD ["nodemon", "--legacy-watch", "src/server.js"]

# Betrieb: Code und Pakete stecken im Image, läuft ohne Root-Rechte
FROM base AS prod
ENV NODE_ENV=production
RUN npm ci --omit=dev && npm cache clean --force
COPY --chown=node:node . .
RUN mkdir -p uploads && chown node:node uploads
USER node
EXPOSE 3000
CMD ["node", "src/server.js"]
