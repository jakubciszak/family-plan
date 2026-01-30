# Claude Code Skills - Family Plan

This directory contains Claude Code skills that extend the AI assistant's capabilities for this project.

## Project-Specific Skills

These skills are tailored for the Family Plan application:

| Skill | Description | When to Use |
|-------|-------------|-------------|
| [symfony-ddd](./symfony-ddd/SKILL.md) | Hexagonal Architecture, DDD, CQRS | Backend feature development |
| [tdd-php](./tdd-php/SKILL.md) | Test-Driven Development | Writing tests, PHPUnit, Behat |
| [react-frontend](./react-frontend/SKILL.md) | React 18 development | Frontend components, Playwright |
| [react-native-expo](./react-native-expo/SKILL.md) | React Native with Expo | Mobile app development |
| [docker-makefile](./docker-makefile/SKILL.md) | Docker Compose, Makefile | Environment management |
| [database-migrations](./database-migrations/SKILL.md) | Doctrine ORM, PostgreSQL | Database schema, migrations |
| [api-design](./api-design/SKILL.md) | REST API, OpenAPI | API endpoints, documentation |
| [ci-cd-github](./ci-cd-github/SKILL.md) | GitHub Actions CI/CD | Workflows, testing pipelines |

## Recommended External Skills

Skills from [VoltAgent/awesome-agent-skills](https://github.com/VoltAgent/awesome-agent-skills) that complement this project:

### Development & Testing

| Skill | Author | Repository | Description |
|-------|--------|------------|-------------|
| **webapp-testing** | Anthropic | [Link](https://github.com/anthropics/courses/tree/master/skills/webapp-testing) | Web application testing strategies |
| **mcp-builder** | Anthropic | [Link](https://github.com/anthropics/courses/tree/master/skills/mcp-builder) | Build Model Context Protocol servers |

### Mobile Development (Expo)

| Skill | Author | Repository | Description |
|-------|--------|------------|-------------|
| **expo-design** | Expo | [Link](https://github.com/expo/expo/tree/main/.claude/skills/design) | Mobile app design with Expo |
| **expo-deployment** | Expo | [Link](https://github.com/expo/expo/tree/main/.claude/skills/deployment) | App deployment with EAS |

### Security

| Skill | Author | Repository | Description |
|-------|--------|------------|-------------|
| **security-review** | Trail of Bits | [Link](https://github.com/trailofbits/ai-skills) | General security code review |
| **api-security** | Trail of Bits | [Link](https://github.com/trailofbits/ai-skills) | API security best practices |
| **crypto-review** | Trail of Bits | [Link](https://github.com/trailofbits/ai-skills) | Cryptography implementation review |

### Documentation

| Skill | Author | Repository | Description |
|-------|--------|------------|-------------|
| **docx** | Anthropic | [Link](https://github.com/anthropics/courses/tree/master/skills/docx) | Create/edit Word documents |
| **pdf** | Anthropic | [Link](https://github.com/anthropics/courses/tree/master/skills/pdf) | Create/analyze PDF files |

### Infrastructure

| Skill | Author | Repository | Description |
|-------|--------|------------|-------------|
| **docker-best-practices** | Community | Various | Container optimization |
| **terraform-practices** | Community | Various | Infrastructure as Code |

## Installing External Skills

To install an external skill:

1. Clone or download the skill from its repository
2. Copy the skill folder to `.claude/skills/`
3. Ensure it contains a valid `SKILL.md` file

Example:
```bash
# Clone the skill repository
git clone https://github.com/trailofbits/ai-skills /tmp/ai-skills

# Copy desired skill
cp -r /tmp/ai-skills/security-review .claude/skills/

# Clean up
rm -rf /tmp/ai-skills
```

## Skill File Format

Each skill follows this structure:

```
skill-name/
├── SKILL.md          # Main skill file (required)
├── examples/         # Optional examples
└── resources/        # Optional supporting files
```

### SKILL.md Format

```markdown
---
name: skill-name
description: Clear description of what this skill does and when to use it
---

# Skill Title

Instructions and guidelines for Claude to follow when this skill is active.

## Usage Examples
...

## Best Practices
...
```

## Creating New Skills

When creating a new skill for this project:

1. Create a folder in `.claude/skills/` with a descriptive name
2. Add a `SKILL.md` file with frontmatter (name, description)
3. Write clear, actionable instructions
4. Include code examples specific to this project
5. Reference existing patterns from the codebase

## Skill Activation

Skills are automatically available to Claude Code when placed in:
- **Project level**: `.claude/skills/` (this directory)
- **Global level**: `~/.claude/skills/`

Project-level skills take precedence over global skills.

## Resources

- [Awesome Agent Skills](https://github.com/VoltAgent/awesome-agent-skills) - Curated list of AI coding skills
- [Claude Code Documentation](https://docs.anthropic.com/claude-code) - Official documentation
- [Contributing Guide](https://github.com/VoltAgent/awesome-agent-skills/blob/main/CONTRIBUTING.md) - How to create and share skills
