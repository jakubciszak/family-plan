# Development Dockerfile for React Frontend
FROM node:20-alpine

WORKDIR /app

# Install dependencies
COPY package*.json ./
RUN npm install

# Copy application source
COPY . .

# Expose development server port
EXPOSE 3000

# Start development server with hot reload
CMD ["npm", "start"]
