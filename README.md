# SecretSpot KB tool

Pack of tools to manage SecretSpot KB

## Docker Image

### Build

```
docker buildx build . --tag isxam/secret-spot-kb-tool:[tag]
```

### Push
```
docker push isxam/secret-spot-kb-tool:latest
```

### Example
```
docker run -v ../kb:/app/kb isxam/secret-spot-kb-tool:latest php bin/cli kb:repo:pack kb kb/kb-packed.yaml
```
