# [🙊] Secret Spot | KB tool

Tools to manage Secret Spot Knowledge Bases

## Docker Image

### Build
```
docker buildx build . --tag isxam/secret-spot-kb-tool:[tag]
```

### Example
```
docker run -v ../kb:/app/kb isxam/secret-spot-kb-tool:latest php bin/cli kb:repo:pack kb kb/kb-packed.yaml
```
