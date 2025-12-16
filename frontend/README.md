# Family Plan Frontend

React-based Single Page Application (SPA) for the Family Plan task management system.

## Overview

This is a standalone React application that communicates with the Family Plan backend API. The frontend is completely separated from the backend and can be deployed as an independent container.

## Technology Stack

- React 18.2
- Webpack 5
- Babel
- CSS

## Development

### Prerequisites

- Node.js 20+
- npm

### Setup

1. Install dependencies:
```bash
npm install
```

2. Configure environment:
```bash
cp .env.example .env
# Edit .env and set REACT_APP_API_URL to your backend API URL
```

3. Start development server:
```bash
npm start
```

The application will be available at http://localhost:3000 with hot reload enabled.

### Available Scripts

- `npm start` - Start development server with hot reload
- `npm run dev` - Build in development mode
- `npm run build` - Build for production
- `npm run watch` - Watch mode for development

## Docker Development

Build and run with Docker:

```bash
# Development
docker build -t family-plan-frontend:dev -f Dockerfile .
docker run -p 3000:3000 -v $(pwd):/app family-plan-frontend:dev

# Production
docker build -t family-plan-frontend:prod -f Dockerfile.prod .
docker run -p 8080:80 family-plan-frontend:prod
```

## Docker Compose

Use with the main docker-compose setup:

```bash
# Development
docker compose up frontend

# Production
docker compose -f compose.yaml -f compose.prod.yaml up frontend
```

## Configuration

### Environment Variables

- `REACT_APP_API_URL` - Backend API base URL (default: http://localhost:8080)

### API Proxy

In development mode, the webpack dev server proxies `/api` requests to the backend API configured via `API_URL` environment variable.

## Building for Production

```bash
npm run build
```

This creates an optimized production build in the `dist/` directory with:
- Minified JavaScript and CSS
- Source maps for debugging
- Cache-busting file names
- Code splitting for optimal loading

## Deployment

The production Docker image uses nginx to serve the static files:

1. Build the production image:
```bash
docker build -t family-plan-frontend:latest -f Dockerfile.prod .
```

2. Run the container:
```bash
docker run -p 80:80 family-plan-frontend:latest
```

## Features

- User authentication and session management
- Task list view with filtering
- Real-time task updates
- Responsive design
- Error handling and loading states

## API Integration

The frontend communicates with the backend REST API:

- `POST /api/auth/login` - User authentication
- `POST /api/auth/logout` - User logout
- `GET /api/auth/me` - Get current user
- `GET /api/tasks` - Get tasks list
- `POST /api/tasks` - Create new task
- `PUT /api/tasks/{id}` - Update task
- `DELETE /api/tasks/{id}` - Delete task

See the main API documentation for complete endpoint details.

## Project Structure

```
frontend/
├── public/          # Static files
│   └── index.html   # HTML template
├── src/             # Source code
│   ├── App.jsx      # Main application component
│   ├── index.jsx    # Application entry point
│   ├── pages/       # Page components
│   ├── services/    # API client and services
│   └── styles/      # CSS styles
├── dist/            # Production build output
├── Dockerfile       # Development Docker image
├── Dockerfile.prod  # Production Docker image
├── nginx.conf       # Nginx configuration for production
├── webpack.config.js # Webpack configuration
└── package.json     # Dependencies and scripts
```

## Contributing

When making changes:

1. Follow React best practices
2. Use functional components with hooks
3. Keep components small and focused
4. Add proper error handling
5. Test with the backend API

## License

UNLICENSED - Private project
