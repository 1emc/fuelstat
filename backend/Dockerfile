# Base Stage
FROM node:20-alpine AS base
WORKDIR /usr/src/app

# Optional, aber oft praktisch für native deps (bcrypt etc.)
# RUN apk add --no-cache libc6-compat

COPY package*.json ./

# Development Stage
FROM base AS dev
ENV NODE_ENV=development
RUN npm install
RUN npm install -g nodemon@3
COPY . .
EXPOSE 3000
CMD ["nodemon", "--legacy-watch", "src/server.js"]

# Production Stage
FROM node:20-alpine AS prod
WORKDIR /usr/src/app
ENV NODE_ENV=production

COPY package*.json ./
RUN npm ci --omit=dev

COPY --chown=node:node . .
USER node

EXPOSE 3000
CMD ["node", "src/server.js"]

