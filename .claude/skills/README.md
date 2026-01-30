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

## Installed External Skills

Skills from [VoltAgent/awesome-agent-skills](https://github.com/VoltAgent/awesome-agent-skills) installed in `external/` directory:

### Anthropic Skills

| Skill | Location | Description |
|-------|----------|-------------|
| **webapp-testing** | [external/webapp-testing](./external/webapp-testing/SKILL.md) | Playwright-based web app testing toolkit |
| **mcp-builder** | [external/mcp-builder](./external/mcp-builder/SKILL.md) | Build Model Context Protocol servers |
| **docx** | [external/docx](./external/docx/SKILL.md) | Create and edit Word documents |
| **pdf** | [external/pdf](./external/pdf/SKILL.md) | Create and analyze PDF files |

### Expo Skills (Mobile Development)

| Skill | Location | Description |
|-------|----------|-------------|
| **expo-app-design** | [external/expo-app-design](./external/expo-app-design/) | Complete guide for building Expo apps |
| ↳ building-native-ui | [skills/building-native-ui](./external/expo-app-design/skills/building-native-ui/SKILL.md) | Native UI components, routing, animations |
| ↳ expo-api-routes | [skills/expo-api-routes](./external/expo-app-design/skills/expo-api-routes/SKILL.md) | API routes in Expo Router |
| ↳ expo-dev-client | [skills/expo-dev-client](./external/expo-app-design/skills/expo-dev-client/SKILL.md) | Custom development client setup |
| ↳ expo-tailwind-setup | [skills/expo-tailwind-setup](./external/expo-app-design/skills/expo-tailwind-setup/SKILL.md) | NativeWind/Tailwind configuration |
| ↳ native-data-fetching | [skills/native-data-fetching](./external/expo-app-design/skills/native-data-fetching/SKILL.md) | Data fetching patterns |
| ↳ use-dom | [skills/use-dom](./external/expo-app-design/skills/use-dom/SKILL.md) | DOM components in React Native |
| **expo-deployment** | [external/expo-deployment](./external/expo-deployment/) | App deployment with EAS |
| ↳ expo-cicd-workflows | [skills/expo-cicd-workflows](./external/expo-deployment/skills/expo-cicd-workflows/SKILL.md) | CI/CD workflows for Expo |
| ↳ expo-deployment | [skills/expo-deployment](./external/expo-deployment/skills/expo-deployment/SKILL.md) | Deployment strategies |

### Trail of Bits Security Skills

| Skill | Location | Description |
|-------|----------|-------------|
| **property-based-testing** | [external/property-based-testing](./external/property-based-testing/skills/property-based-testing/SKILL.md) | Property-based testing patterns |
| **static-analysis** | [external/static-analysis](./external/static-analysis/) | Static analysis tools |
| ↳ codeql | [skills/codeql](./external/static-analysis/skills/codeql/SKILL.md) | CodeQL query writing |
| ↳ semgrep | [skills/semgrep](./external/static-analysis/skills/semgrep/SKILL.md) | Semgrep rule patterns |
| ↳ sarif-parsing | [skills/sarif-parsing](./external/static-analysis/skills/sarif-parsing/SKILL.md) | SARIF output parsing |
| **semgrep-rule-creator** | [external/semgrep-rule-creator](./external/semgrep-rule-creator/skills/semgrep-rule-creator/SKILL.md) | Create custom Semgrep rules |
| **differential-review** | [external/differential-review](./external/differential-review/skills/differential-review/SKILL.md) | Security-focused code review |

## Installing Additional External Skills

To install more skills from awesome-agent-skills:

```bash
# Clone the skill repository
git clone https://github.com/anthropics/skills /tmp/anthropic-skills

# Copy desired skill to external/
cp -r /tmp/anthropic-skills/skills/skill-name .claude/skills/external/

# Clean up
rm -rf /tmp/anthropic-skills
```

**Skill repositories:**
- Anthropic: `https://github.com/anthropics/skills`
- Expo: `https://github.com/expo/skills`
- Trail of Bits: `https://github.com/trailofbits/skills`

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
